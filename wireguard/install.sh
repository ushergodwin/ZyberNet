#!/bin/bash

# Script to install WireGuard VPN on Ubuntu and configure it as a server
# Subnet used: 10.57.123.0/24

SERVER_VPN_IP="10.57.123.1/24"
VPN_SUBNET="10.57.123.0/24"
WG_INTERFACE="wg0"
WG_PORT=51820
WG_CONFIG="/etc/wireguard/${WG_INTERFACE}.conf"

echo "📦 Installing WireGuard..."
apt update && apt install -y wireguard qrencode

echo "🔐 Generating server keys..."
umask 077
wg genkey | tee /etc/wireguard/server_private.key | wg pubkey > /etc/wireguard/server_public.key

SERVER_PRIVATE_KEY=$(cat /etc/wireguard/server_private.key)

echo "⚙️ Creating WireGuard config..."

cat > "$WG_CONFIG" <<EOF
[Interface]
Address = ${SERVER_VPN_IP}
ListenPort = ${WG_PORT}
PrivateKey = ${SERVER_PRIVATE_KEY}
SaveConfig = true

# Peers (clients/routers) will be added manually using add-peer.sh
EOF

chmod 600 "$WG_CONFIG"

echo "🛡️ Enabling IP forwarding..."
sysctl -w net.ipv4.ip_forward=1
echo 'net.ipv4.ip_forward=1' >> /etc/sysctl.conf

echo "🔥 Setting up firewall (iptables)..."
iptables -A FORWARD -i $WG_INTERFACE -j ACCEPT
iptables -A FORWARD -o $WG_INTERFACE -j ACCEPT
iptables -t nat -A POSTROUTING -s $VPN_SUBNET -o eth0 -j MASQUERADE

# Persist iptables
apt install -y iptables-persistent
netfilter-persistent save
netfilter-persistent reload

echo "🚀 Enabling and starting WireGuard..."
systemctl enable "wg-quick@${WG_INTERFACE}"
systemctl start "wg-quick@${WG_INTERFACE}"

echo "✅ WireGuard VPN is installed and running."
echo "Server VPN IP: 10.57.123.1/24"
