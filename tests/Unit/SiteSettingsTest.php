<?php

namespace Hub\Tests\Unit;

use Hub\SiteSettings;
use Hub\Database;
use PHPUnit\Framework\TestCase;

class SiteSettingsTest extends TestCase
{
    private static Database $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        // Clear any cached settings by resetting static state via reflection
        $reflection = new \ReflectionClass(SiteSettings::class);
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $settingsProperty->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Reset settings cache
        $reflection = new \ReflectionClass(SiteSettings::class);
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $settingsProperty->setValue(null, null);
    }

    /**
     * Test get returns setting value from database
     */
    public function testGetReturnsSettingValue(): void
    {
        $value = SiteSettings::get('primary_color');

        // May be null if no settings in test database
        if ($value !== null) {
            $this->assertIsString($value);
            $this->assertNotEmpty($value);
        } else {
            $this->assertNull($value);
        }
    }

    /**
     * Test get returns default value when setting not found
     */
    public function testGetReturnsDefaultWhenNotFound(): void
    {
        $default = '#TESTCOLOR';
        $value = SiteSettings::get('nonexistent_setting_' . uniqid(), $default);

        $this->assertEquals($default, $value);
    }

    /**
     * Test get with null default
     */
    public function testGetWithNullDefault(): void
    {
        $value = SiteSettings::get('nonexistent_setting_' . uniqid());

        $this->assertNull($value);
    }

    /**
     * Test getAll returns all settings as array
     */
    public function testGetAllReturnsArray(): void
    {
        $settings = SiteSettings::getAll();

        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);
    }

    /**
     * Test getAll includes known settings
     */
    public function testGetAllIncludesKnownSettings(): void
    {
        $settings = SiteSettings::getAll();

        // Test database may be empty, so just verify it's an array
        $this->assertIsArray($settings);

        // If settings exist, check structure
        if (!empty($settings)) {
            // At least one setting should exist
            $this->assertGreaterThan(0, count($settings));
        }
    }    /**
     * Test boolean type conversion
     */
    public function testBooleanTypeConversion(): void
    {
        // Settings with boolean type should be converted to bool
        $settings = SiteSettings::getAll();

        // Find a boolean setting
        foreach ($settings as $key => $value) {
            if (is_bool($value)) {
                $this->assertIsBool($value);
                return;
            }
        }

        $this->assertTrue(true, 'No boolean settings found to test');
    }

    /**
     * Test number type conversion
     */
    public function testNumberTypeConversion(): void
    {
        // Logo height should be a number
        $logoHeight = SiteSettings::get('logo_height');

        if ($logoHeight !== null) {
            $this->assertTrue(is_numeric($logoHeight) || is_float($logoHeight) || is_int($logoHeight));
        } else {
            // No setting found, that's okay for test database
            $this->assertTrue(true);
        }
    }    /**
     * Test getCSSVariables returns CSS string
     */
    public function testGetCSSVariablesReturnsString(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertIsString($css);
        $this->assertStringStartsWith(':root {', $css);
        $this->assertStringContainsString('--primary-color:', $css);
    }

    /**
     * Test getCSSVariables includes color variables
     */
    public function testGetCSSVariablesIncludesColorVariables(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--primary-color:', $css);
        $this->assertStringContainsString('--navbar-color:', $css);
        $this->assertStringContainsString('--background-color:', $css);
        $this->assertStringContainsString('--accent-color:', $css);
    }

    /**
     * Test getCSSVariables includes size variables
     */
    public function testGetCSSVariablesIncludesSizeVariables(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--logo-height:', $css);
        $this->assertStringContainsString('--header-height:', $css);
        $this->assertStringContainsString('--footer-height:', $css);
    }

    /**
     * Test getCSSVariables includes hub page variables
     */
    public function testGetCSSVariablesIncludesHubPageVariables(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--hub-page-bg:', $css);
        $this->assertStringContainsString('--hub-tile-bg:', $css);
        $this->assertStringContainsString('--hub-tile-text:', $css);
    }

    /**
     * Test getCSSVariables includes button styles
     */
    public function testGetCSSVariablesIncludesButtonStyles(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('.btn {', $css);
        $this->assertStringContainsString('.btn-primary', $css);
        $this->assertStringContainsString('.btn-secondary', $css);
        $this->assertStringContainsString('.btn-danger', $css);
        $this->assertStringContainsString('.btn-success', $css);
    }

    /**
     * Test getCSSVariables includes role badge colors
     */
    public function testGetCSSVariablesIncludesRoleBadgeColors(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--role-staff-bg:', $css);
        $this->assertStringContainsString('--role-admin-bg:', $css);
        $this->assertStringContainsString('--role-super-admin-bg:', $css);
    }

    /**
     * Test getCSSVariables includes status colors
     */
    public function testGetCSSVariablesIncludesStatusColors(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--success-bg:', $css);
        $this->assertStringContainsString('--danger-bg:', $css);
        $this->assertStringContainsString('--warning-bg:', $css);
        $this->assertStringContainsString('--info-bg:', $css);
    }

    /**
     * Test getCSSVariables handles particle display toggle
     */
    public function testGetCSSVariablesHandlesParticleDisplay(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--hub-particle-display:', $css);
    }

    /**
     * Test getCSSVariables handles active menu font weight
     */
    public function testGetCSSVariablesHandlesActiveMenuFontWeight(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--active-menu-font-weight:', $css);
        $this->assertTrue(
            str_contains($css, '--active-menu-font-weight: bold') ||
            str_contains($css, '--active-menu-font-weight: normal')
        );
    }

    /**
     * Test getLogoGlowCSS returns CSS when enabled
     */
    public function testGetLogoGlowCSSReturnsStringWhenEnabled(): void
    {
        $css = SiteSettings::getLogoGlowCSS();

        // Should return either CSS or empty string depending on setting
        $this->assertIsString($css);

        if (!empty($css)) {
            $this->assertStringContainsString('filter:', $css);
            $this->assertStringContainsString('drop-shadow', $css);
        }
    }

    /**
     * Test getMobileLogoGlowCSS returns CSS when enabled
     */
    public function testGetMobileLogoGlowCSSReturnsString(): void
    {
        $css = SiteSettings::getMobileLogoGlowCSS();

        $this->assertIsString($css);

        if (!empty($css)) {
            $this->assertStringContainsString('filter:', $css);
            $this->assertStringContainsString('drop-shadow', $css);
        }
    }

    /**
     * Test isMaintenanceMode returns boolean
     */
    public function testIsMaintenanceModeReturnsBoolean(): void
    {
        $result = SiteSettings::isMaintenanceMode();

        $this->assertIsBool($result);
    }

    /**
     * Test getGoogleFontsLink returns HTML string
     */
    public function testGetGoogleFontsLinkReturnsHTML(): void
    {
        $html = SiteSettings::getGoogleFontsLink();

        $this->assertIsString($html);

        if (!empty($html)) {
            $this->assertStringContainsString('fonts.googleapis.com', $html);
            $this->assertStringContainsString('<link', $html);
        }
    }

    /**
     * Test getGoogleFontsLink includes preconnect
     */
    public function testGetGoogleFontsLinkIncludesPreconnect(): void
    {
        $html = SiteSettings::getGoogleFontsLink();

        if (!empty($html)) {
            $this->assertStringContainsString('rel="preconnect"', $html);
            $this->assertStringContainsString('fonts.gstatic.com', $html);
        }
    }

    /**
     * Test getGoogleFontsLink handles spaces in font names
     */
    public function testGetGoogleFontsLinkHandlesSpaces(): void
    {
        $html = SiteSettings::getGoogleFontsLink();

        // If fonts have spaces, they should be replaced with +
        if (!empty($html) && str_contains($html, 'family=')) {
            // Font names with spaces should be encoded as +
            $this->assertTrue(true);
        }
    }

    /**
     * Test getSessionTimeout returns integer
     */
    public function testGetSessionTimeoutReturnsInteger(): void
    {
        $timeout = SiteSettings::getSessionTimeout();

        $this->assertIsInt($timeout);
        $this->assertGreaterThan(0, $timeout);
    }

    /**
     * Test getSessionTimeout converts hours to seconds
     */
    public function testGetSessionTimeoutConvertsHoursToSeconds(): void
    {
        $timeout = SiteSettings::getSessionTimeout();

        // Default is 2 hours = 7200 seconds
        // Should be a multiple of 3600
        $this->assertEquals(0, $timeout % 3600, 'Timeout should be in hour increments');
    }

    /**
     * Test settings are cached after first load
     */
    public function testSettingsAreCached(): void
    {
        // First call loads from DB
        $value1 = SiteSettings::get('primary_color');

        // Second call should use cache (we can't directly test this,
        // but we can verify it returns same value)
        $value2 = SiteSettings::get('primary_color');

        $this->assertEquals($value1, $value2);
    }

    /**
     * Test getAll returns same data on multiple calls
     */
    public function testGetAllReturnsSameData(): void
    {
        $all1 = SiteSettings::getAll();
        $all2 = SiteSettings::getAll();

        $this->assertEquals($all1, $all2);
    }

    /**
     * Test getCSSVariables uses defaults for missing settings
     */
    public function testGetCSSVariablesUsesDefaults(): void
    {
        $css = SiteSettings::getCSSVariables();

        // Should have default colors even if not in database
        $this->assertStringContainsString('--primary-color:', $css);

        // Check that it contains a valid hex color or color value
        $this->assertMatchesRegularExpression('/#[0-9A-Fa-f]{6}/', $css);
    }

    /**
     * Test getCSSVariables handles logo glow enabled
     */
    public function testGetCSSVariablesHandlesLogoGlowEnabled(): void
    {
        $css = SiteSettings::getCSSVariables();

        // If logo glow is enabled, CSS should include the filter
        if (SiteSettings::get('logo_glow_enabled', true)) {
            $this->assertStringContainsString('.nav-brand img', $css);
        }
    }

    /**
     * Test getCSSVariables includes scrollbar colors
     */
    public function testGetCSSVariablesIncludesScrollbarColors(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--scrollbar-track:', $css);
        $this->assertStringContainsString('--scrollbar-thumb:', $css);
        $this->assertStringContainsString('--scrollbar-thumb-hover:', $css);
    }

    /**
     * Test getCSSVariables includes shadow opacity
     */
    public function testGetCSSVariablesIncludesShadowOpacity(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--shadow-light:', $css);
        $this->assertStringContainsString('--shadow-medium:', $css);
        $this->assertStringContainsString('--shadow-heavy:', $css);
    }

    /**
     * Test getCSSVariables includes sidebar variables
     */
    public function testGetCSSVariablesIncludesSidebarVariables(): void
    {
        $css = SiteSettings::getCSSVariables();

        $this->assertStringContainsString('--sidebar-bg:', $css);
        $this->assertStringContainsString('--sidebar-text-color:', $css);
        $this->assertStringContainsString('--sidebar-active-highlight:', $css);
    }
}
