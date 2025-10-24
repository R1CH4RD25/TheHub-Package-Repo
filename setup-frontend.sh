#!/bin/bash

# The Hub - Modern Frontend Integration Setup
# Installs npm packages and builds frontend assets

set -e

echo "🚀 The Hub - Modern Frontend Integration Setup"
echo "=============================================="
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed"
    echo "Please install Node.js 18+ from https://nodejs.org/"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed"
    echo "Please install npm (comes with Node.js)"
    exit 1
fi

echo "✅ Node.js version: $(node --version)"
echo "✅ npm version: $(npm --version)"
echo ""

# Install dependencies
echo "📦 Installing npm packages..."
npm install
echo ""

# Create dist directory
mkdir -p public/assets/dist

# Build frontend bundles
echo "🔨 Building frontend bundles..."
npm run build
echo ""

echo "✅ Frontend integration complete!"
echo ""
echo "📋 What was installed:"
echo "   • Bootstrap 5.3.3 - Modern CSS framework"
echo "   • Bootstrap Icons 1.11.3 - Comprehensive icon set"
echo "   • Alpine.js 3.14.1 - Lightweight reactive framework"
echo "   • HTMX 1.9.12 - HTML-over-the-wire for dynamic updates"
echo "   • SweetAlert2 11.10.8 - Beautiful modals and alerts"
echo "   • AOS 2.3.4 - Animate On Scroll library"
echo "   • Chart.js 4.4.2 - Powerful charting library"
echo "   • ApexCharts 3.48.0 - Modern interactive charts"
echo "   • Flatpickr 4.6.13 - Datetime picker"
echo "   • Tom Select 2.3.1 - Advanced select/autocomplete"
echo "   • DataTables 2.0.3 - Advanced table features"
echo "   • Quill 2.0.0 - Rich text editor"
echo "   • Notyf 3.10.0 - Toast notifications"
echo "   • Axios 1.6.8 - HTTP client"
echo "   • Day.js 1.11.10 - Lightweight date library"
echo "   • Lodash 4.17.21 - Utility library"
echo "   • Tippy.js 6.3.7 - Tooltips and popovers"
echo ""
echo "🌐 The Hub will automatically use CDN versions as fallback"
echo "   if local bundles are not available."
echo ""
echo "💡 Usage:"
echo "   • Libraries are auto-loaded via Layout::renderHead()"
echo "   • Access via window.TheHub global object"
echo "   • Examples: TheHub.notify(), TheHub.confirm()"
echo ""
