#!/bin/bash

# Shared CSS (used across all interfaces)
mv enterprise-design-system.css shared/
mv enterprise-components.css shared/
mv header.css shared/
mv footer.css shared/
mv modals.css shared/

# Hub-specific CSS
mv hub.css hub/
mv hub-modern.css hub/
mv sections.css hub/
mv modules.css hub/
mv modules-modern.css hub/

# Management Console CSS
mv management.css mgmt/
mv dynamic-sections.css mgmt/

# Admin Dashboard CSS
mv admin.css admin/
mv admin-modern.css admin/
mv admin-theme.css admin/
mv admin-colors.css admin/

echo "✓ CSS files organized into subdirectories"
ls -lR shared/ hub/ mgmt/ admin/ | grep "\.css$" | wc -l
echo "total CSS files moved"
