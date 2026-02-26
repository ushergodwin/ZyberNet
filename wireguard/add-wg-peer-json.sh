#!/bin/bash

# Add a new MikroTik router as a WireGuard peer to the VPN
# Returns JSON for API use

WG_INTERFACE="wg0"
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"
SUBNET_BASE="10.57.123"
LAST_USED_FILE="/etc/wireguard/last_used_ip"
PEER_NAME=$1

if [ -z "$PEER_NAME" ]; then
  echo '{"error":"Missing peer name"}'
  exit 1
fi

# Detect server public IP
SERVER_PUBLIC_IP=$(hostname -I | awk '{print $1}')
if [[ -z "$SERVER_PUBLIC_IP" ]]; then
  echo '{"error":"Could not detect server public IP"}'
  exit 1
fi

# Ensure last used IP tracker exists
if [ ! -f "$LAST_USED_FILE" ]; then
  echo "2" > "$LAST_USED_FILE"
fi

# Find next free IP in config
NEXT_IP=$(cat "$LAST_USED_FILE")

while grep -q "${SUBNET_BASE}.${NEXT_IP}/32" "$WG_CONFIG"; do
  NEXT_IP=$((NEXT_IP + 1))
done

NEW_PEER_IP="${SUBNET_BASE}.${NEXT_IP}"
echo $((NEXT_IP + 1)) > "$LAST_USED_FILE"

# Generate keys
PEER_PRIVATE_KEY=$(wg genkey)
PEER_PUBLIC_KEY=$(echo "$PEER_PRIVATE_KEY" | wg pubkey)

SERVER_PUBLIC_KEY=$(cat /etc/wireguard/server_public.key)

# Append peer to wg0.conf
cat >> "$WG_CONFIG" <<EOF

# ${PEER_NAME}
[Peer]
PublicKey = ${PEER_PUBLIC_KEY}
AllowedIPs = ${NEW_PEER_IP}/32
PersistentKeepalive = 25
EOF

# Apply changes live
wg addconf $WG_INTERFACE <(wg-quick strip $WG_INTERFACE)

# Generate mikrotik port
BASE_PORT=29725
PORT_INCREMENT=$((RANDOM % 20000))
MIKROTIK_PORT=$((BASE_PORT + PORT_INCREMENT))

# JSON OUTPUT
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
    "create_interface": "/interface wireguard add name=wg-superspot-${PEER_NAME} listen-port=${MIKROTIK_PORT} private-key=\\"${PEER_PRIVATE_KEY}\\"",
    "assign_ip": "/ip address add address=${NEW_PEER_IP}/32 interface=wg-superspot-${PEER_NAME}",
    "add_peer": "/interface wireguard peers add interface=wg-superspot-${PEER_NAME} public-key=\\"${SERVER_PUBLIC_KEY}\\" endpoint-address=${SERVER_PUBLIC_IP} endpoint-port=51820 allowed-address=10.57.123.1/32 persistent-keepalive=25",
    "route": "/ip route add dst-address=10.57.123.0/24 gateway=wg-superspot-${PEER_NAME}",
    "firewall_allow_udp": "/ip firewall filter add chain=input action=accept protocol=udp dst-port=${MIKROTIK_PORT} comment=\\"Allow WireGuard\\"",
    "nat_masquerade": "/ip firewall nat add chain=srcnat action=masquerade out-interface=wg-superspot-${PEER_NAME}",
    "firewall_input_accept": "/ip firewall filter add chain=input action=accept in-interface=wg-superspot-${PEER_NAME} comment=\\"Allow WG input\\"",
    "firewall_forward_in": "/ip firewall filter add chain=forward action=accept in-interface=wg-superspot-${PEER_NAME} comment=\\"Allow WG forward in\\"",
    "firewall_forward_out": "/ip firewall filter add chain=forward action=accept out-interface=wg-superspot-${PEER_NAME} comment=\\"Allow WG forward out\\""
  }
}
EOF
