#!/bin/bash

# Add a new MikroTik router as a WireGuard peer to the VPN
# Secure subnet 10.57.123.0/24

WG_INTERFACE="wg0"
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"
SUBNET_BASE="10.57.123"
LAST_USED_FILE="/etc/wireguard/last_used_ip"
PEER_NAME=$1

if [ -z "$PEER_NAME" ]; then
  echo "Usage: $0 <peer-name>"
  exit 1
fi

# Public IP of the server
SERVER_PUBLIC_IP=$(curl -s https://ipinfo.io/ip)
if [[ -z "$SERVER_PUBLIC_IP" ]]; then
  echo "Error: Could not detect public IP."
  exit 1
fi

# Ensure last_used_ip file exists
if [ ! -f "$LAST_USED_FILE" ]; then
    echo "2" > "$LAST_USED_FILE"
fi

# Determine next free IP by checking wg0.conf
NEXT_IP=$(cat "$LAST_USED_FILE")

while grep -q "${SUBNET_BASE}.${NEXT_IP}/32" "$WG_CONFIG"; do
    NEXT_IP=$((NEXT_IP + 1))
done

NEW_PEER_IP="${SUBNET_BASE}.${NEXT_IP}"
echo $((NEXT_IP + 1)) > "$LAST_USED_FILE"

# Generate peer keys
PEER_PRIVATE_KEY=$(wg genkey)
PEER_PUBLIC_KEY=$(echo "$PEER_PRIVATE_KEY" | wg pubkey)

# Server Public Key (verified from your system)
SERVER_PUBLIC_KEY=$(cat /etc/wireguard/server_public.key)

echo "🔧 Adding peer [$PEER_NAME] with IP [$NEW_PEER_IP]"

# Add peer to wg0.conf
cat >> "$WG_CONFIG" <<EOF

# ${PEER_NAME}
[Peer]
PublicKey = ${PEER_PUBLIC_KEY}
AllowedIPs = ${NEW_PEER_IP}/32
PersistentKeepalive = 25
EOF

# Apply changes live
wg addconf $WG_INTERFACE <(wg-quick strip $WG_INTERFACE)

# MikroTik port selection (use ports already used by other routers)
# Your routers use ports: 51820 and 29725 → pick 29725 + increment
BASE_PORT=29725
PORT_INCREMENT=$((RANDOM % 20000))
MIKROTIK_PORT=$((BASE_PORT + PORT_INCREMENT))

echo ""
echo "---------------------------------------------------------"
echo "✅ MikroTik WireGuard Setup Instructions (RouterOS CLI)"
echo "---------------------------------------------------------"
echo ""
echo "/interface wireguard add name=wg-superspot-${PEER_NAME} private-key=\"${PEER_PRIVATE_KEY}\" listen-port=${MIKROTIK_PORT}"
echo "/ip address add address=${NEW_PEER_IP}/32 interface=wg-superspot-${PEER_NAME}"
echo "/interface wireguard peers add interface=wg-superspot-${PEER_NAME} public-key=\"${SERVER_PUBLIC_KEY}\" endpoint-address=${SERVER_PUBLIC_IP} endpoint-port=51820 allowed-address=10.57.123.1/32 persistent-keepalive=25"
echo ""
echo "⚠ IMPORTANT: On the server, the peer is already added."
echo "Router will auto-connect after you apply the above commands."
