#!/bin/bash

# Add a new MikroTik router as a WireGuard peer to the VPN
# Uses the secure subnet 10.57.123.0/24

WG_INTERFACE="wg0"
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"
SUBNET_BASE="10.57.123"
LAST_USED_FILE="/etc/wireguard/last_used_ip"
PEER_NAME=$1

if [ -z "$PEER_NAME" ]; then
  echo "Usage: $0 <peer-name>"
  exit 1
fi

# Get the VPS public IP automatically
SERVER_PUBLIC_IP=$(curl -s https://ipinfo.io/ip)

if [[ -z "$SERVER_PUBLIC_IP" ]]; then
  echo "Error: Could not detect public IP automatically. Please set manually in the script."
  exit 1
fi

# Determine next available IP
if [ ! -f "$LAST_USED_FILE" ]; then
  echo "2" > "$LAST_USED_FILE"  # Start from .2
fi

NEXT_IP=$(cat "$LAST_USED_FILE")
NEW_PEER_IP="${SUBNET_BASE}.${NEXT_IP}"
echo $((NEXT_IP + 1)) > "$LAST_USED_FILE"

# Generate keys for the peer
PEER_PRIVATE_KEY=$(wg genkey)
PEER_PUBLIC_KEY=$(echo "$PEER_PRIVATE_KEY" | wg pubkey)
SERVER_PUBLIC_KEY=$(cat /etc/wireguard/server_public.key)

# Append to WireGuard config
echo "🔧 Adding peer [$PEER_NAME] with IP [$NEW_PEER_IP]"

cat >> "$WG_CONFIG" <<EOF

# ${PEER_NAME}
[Peer]
PublicKey = ${PEER_PUBLIC_KEY}
AllowedIPs = ${NEW_PEER_IP}/32
EOF

# Reload config
wg addconf $WG_INTERFACE <(wg-quick strip $WG_INTERFACE)

# MikroTik RouterOS instructions
echo ""
echo "✅ MikroTik WireGuard Setup Instructions (RouterOS CLI):"
echo "---------------------------------------------------------"
echo "/interface wireguard add name=wg-superspot private-key=\"$PEER_PRIVATE_KEY\" listen-port=13231"
echo "/ip address add address=${NEW_PEER_IP}/32 interface=wg-superspot"
echo "/interface wireguard peers add interface=wg-superspot public-key=\"$SERVER_PUBLIC_KEY\" endpoint-address=$SERVER_PUBLIC_IP endpoint-port=51820 allowed-address=10.57.123.1/32 persistent-keepalive=25"
