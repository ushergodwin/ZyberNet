#!/bin/bash
# WireGuard VPN Setup & Verification Helper Script
# Save and run this script on your VPS to verify and finalize WireGuard VPN config

WG_INTERFACE="wg0"
VPN_IP="10.57.123.1/24"
WG_PORT=51820
VPN_SUBNET="10.57.123.0/24"

echo "🔧 Assigning VPN IP to WireGuard interface (if not assigned)..."
sudo ip addr add $VPN_IP dev $WG_INTERFACE 2>/dev/null || echo "IP already assigned or interface missing."

echo "📋 Showing WireGuard interface status..."
ip link show $WG_INTERFACE
ip addr show dev $WG_INTERFACE

echo
echo "🔑 Display WireGuard keys and peers info..."
sudo wg show

echo
echo "🔍 Checking if WireGuard service is listening on UDP port $WG_PORT..."
sudo ss -ulpn | grep $WG_PORT || echo "WireGuard service not found listening on port $WG_PORT."

echo
echo "🔥 Listing current iptables rules related to WireGuard port $WG_PORT..."
sudo iptables -L -v -n | grep $WG_PORT || echo "No iptables rules found for port $WG_PORT."

echo
echo "⚙️ Ensure IP forwarding is enabled..."
sysctl net.ipv4.ip_forward

echo
echo "📦 Verify iptables-persistent package is installed..."
dpkg -l | grep iptables-persistent || echo "iptables-persistent package not installed."

echo
echo "💾 Persist iptables rules..."
sudo netfilter-persistent save
sudo netfilter-persistent reload

echo
echo "📡 Monitor incoming WireGuard UDP traffic on eth0 interface (Ctrl+C to stop)..."
echo "Running: sudo tcpdump -i eth0 port $WG_PORT"
sudo tcpdump -i eth0 port $WG_PORT
