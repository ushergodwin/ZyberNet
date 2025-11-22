#!/bin/bash

# Add a new MikroTik router as a WireGuard peer to the VPN
# Returns JSON for use in APIs

WG_INTERFACE="wg0"
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"
SUBNET_BASE="10.57.123"
LAST_USED_FILE="/etc/wireguard/last_used_ip"
PEER_NAME=$1

if [ -z "$PEER_NAME" ]; then
  echo '{"error":"Missing peer name. Usage: script <peer-name>"}'
  exit 1
fi

# Get public IP of server
SERVER_PUBLIC_IP=$(curl -s https://ipinfo.io/ip)
if [[ -z "$SERVER_PUBLIC_IP" ]]; then
  echo '{"error":"Could not detect server public IP"}'
  exit 1
fi

# Ensure IP tracking file exists
if [ ! -f "$LAST_USED_FILE" ]; then
    echo "2" > "$LAST_USED_FILE"
fi

# Find next free IP
NEXT_IP=$(cat "$LAST_USED_FILE")

while grep -q "${SUBNET_BASE}.${NEXT_IP}/32" "$WG_CONFIG"; do
    NEXT_IP=$((NEXT_IP + 1))
done

NEW_PEER_IP="${SUBNET_BASE}.${NEXT_IP}"
echo $((NEXT_IP + 1)) > "$LAST_USED_FILE"

# Generate WG keys
PEER_PRIVATE_KEY=$(wg genkey)
PEER_PUBLIC_KEY=$(echo "$PEER_PRIVATE_KEY" | wg pubkey)

SERVER_PUBLIC_KEY=$(cat /etc/wireguard/server_public.key)

# Append to WG config
cat >> "$WG_CONFIG" <<EOF

# ${PEER_NAME}
[Peer]
PublicKey = ${PEER_PUBLIC_KEY}
AllowedIPs = ${NEW_PEER_IP}/32
PersistentKeepalive = 25
EOF

# Apply config live
wg addconf $WG_INTERFACE <(wg-quick strip $WG_INTERFACE)

# Select MikroTik port strategy
BASE_PORT=29725
PORT_INCREMENT=$((RANDOM % 20000))
MIKROTIK_PORT=$((BASE_PORT + PORT_INCREMENT))

# Return JSON
cat <<EOF
{
  "peer_name": "${PEER_NAME}",
  "peer_ip": "${NEW_PEER_IP}",
  "peer_private_key": "${PEER_PRIVATE_KEY}",
  "peer_public_key": "${PEER_PUBLIC_KEY}",
  "server_public_key": "${SERVER_PUBLIC_KEY}",
  "server_public_ip": "${SERVER_PUBLIC_IP}",
  "server_port": 51820,
  "mikrotik_listen_port": ${MIKROTIK_PORT},
  "mikrotik_instructions": {
    "create_interface": "/interface wireguard add name=wg-superspot-${PEER_NAME} private-key=${PEER_PRIVATE_KEY} listen-port=${MIKROTIK_PORT}",
    "assign_ip": "/ip address add address=${NEW_PEER_IP}/32 interface=wg-superspot-${PEER_NAME}",
    "add_peer": "/interface wireguard peers add interface=wg-superspot-${PEER_NAME} public-key=${SERVER_PUBLIC_KEY} endpoint-address=${SERVER_PUBLIC_IP} endpoint-port=51820 allowed-address=10.57.123.1/32 persistent-keepalive=25"
  }
}
EOF
