#!/bin/bash

# Add a new MikroTik router as a WireGuard peer (CLI version)
# Prints MikroTik commands to the terminal

WG_INTERFACE="wg0"
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"
SUBNET_BASE="10.57.123"
LAST_USED_FILE="/etc/wireguard/last_used_ip"
PEER_NAME=$1

if [ -z "$PEER_NAME" ]; then
  echo "Usage: $0 <peer-name>"
  exit 1
fi

# Detect public IP
SERVER_PUBLIC_IP=$(curl -s https://ipinfo.io/ip)
if [[ -z "$SERVER_PUBLIC_IP" ]]; then
  echo "Error: Could not detect server public IP."
  exit 1
fi

# Ensure tracking file exists
if [ ! -f "$LAST_USED_FILE" ]; then
  echo "2" > "$LAST_USED_FILE"
fi

# Get the next free IP
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

# Update wg0.conf
cat >> "$WG_CONFIG" <<EOF

# ${PEER_NAME}
[Peer]
PublicKey = ${PEER_PUBLIC_KEY}
AllowedIPs = ${NEW_PEER_IP}/32
PersistentKeepalive = 25
EOF

# Apply config live
wg addconf $WG_INTERFACE <(wg-quick strip $WG_INTERFACE)

# Generate port (similar to other routers)
BASE_PORT=29725
PORT_INCREMENT=$((RANDOM % 20000))
MIKROTIK_PORT=$((BASE_PORT + PORT_INCREMENT))

# OUTPUT COMMANDS -------------------------------
echo ""
echo "============================================================"
echo " WireGuard Peer Created: ${PEER_NAME}"
echo "============================================================"
echo ""
echo "Add the following commands to the MikroTik router:"
echo ""

echo "/interface wireguard add name=wg-superspot-${PEER_NAME} listen-port=${MIKROTIK_PORT} private-key=\"${PEER_PRIVATE_KEY}\""
echo "/ip address add address=${NEW_PEER_IP}/32 interface=wg-superspot-${PEER_NAME}"
echo "/interface wireguard peers add interface=wg-superspot-${PEER_NAME} public-key=\"${SERVER_PUBLIC_KEY}\" endpoint-address=${SERVER_PUBLIC_IP} endpoint-port=51820 allowed-address=10.57.123.1/32 persistent-keepalive=25"
echo ""
echo "# Additional commands required:"
echo "/ip route add dst-address=10.57.123.0/24 gateway=wg-superspot-${PEER_NAME}"
echo "/ip firewall filter add chain=input action=accept protocol=udp dst-port=${MIKROTIK_PORT} comment=\"Allow WireGuard\""
echo "/ip firewall nat add chain=srcnat action=masquerade out-interface=wg-superspot-${PEER_NAME}"
echo "/ip firewall filter add chain=input action=accept in-interface=wg-superspot-${PEER_NAME} comment=\"Allow WG input\""
echo "/ip firewall filter add chain=forward action=accept in-interface=wg-superspot-${PEER_NAME} comment=\"Allow WG forward in\""
echo "/ip firewall filter add chain=forward action=accept out-interface=wg-superspot-${PEER_NAME} comment=\"Allow WG forward out\""
echo ""
echo "============================================================"
echo " Done."
echo "============================================================"
