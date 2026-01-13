/**
 * responsive_debug.js — Comprehensive mobile/responsive debugging
 * Monitors viewport size, element overflow, padding issues, flex layouts
 * Console logs all sizing anomalies for mobile development
 */

(function() {
  'use strict';

  const DEBUG_CONFIG = {
    enabled: true,
    logViewportChanges: true,
    logOverflows: true,
    logPaddingIssues: true,
    logFlexIssues: true,
    logTouchTargets: true,
    highlightProblems: true, // Visual highlighting of issues
    mobileBreakpoint: 767.98,
    minTouchTarget: 44, // WCAG minimum
  };

  let lastViewportWidth = window.innerWidth;
  let lastViewportHeight = window.innerHeight;

  /**
   * Log with timestamp and custom styling
   */
  function log(category, message, data = null, style = '') {
    if (!DEBUG_CONFIG.enabled) return;

    const timestamp = new Date().toLocaleTimeString();
    const prefix = `[${timestamp}] [${category}]`;

    if (data) {
      console.log(`%c${prefix} ${message}`, style || 'color: #2563eb; font-weight: bold;', data);
    } else {
      console.log(`%c${prefix} ${message}`, style || 'color: #2563eb; font-weight: bold;');
    }
  }

  /**
   * Check if current viewport is mobile
   */
  function isMobile() {
    return window.innerWidth <= DEBUG_CONFIG.mobileBreakpoint;
  }

  /**
   * Get current breakpoint name based on viewport width
   */
  function getBreakpoint() {
    const width = window.innerWidth;
    if (width <= 575) return 'Small Mobile (≤575px)';
    if (width <= 768) return 'Mobile (≤768px)';
    if (width <= 991) return 'Tablet (769-991px)';
    return 'Desktop (≥992px)';
  }

  /**
   * Display viewport size in console with styling
   */
  function logViewportSize() {
    const width = window.innerWidth;
    const height = window.innerHeight;
    const breakpoint = getBreakpoint();
    
    // Check sidebar size
    const sidebar = document.querySelector('.admin-sidebar, .mgmt-sidebar');
    let sidebarInfo = '';
    if (sidebar) {
      const sidebarWidth = sidebar.offsetWidth;
      const computedStyle = window.getComputedStyle(sidebar);
      const computedWidth = computedStyle.width;
      const display = computedStyle.display;
      const visibility = computedStyle.visibility;
      const gridColumn = computedStyle.gridColumn;
      sidebarInfo = ` | Sidebar: ${sidebarWidth}px (computed: ${computedWidth}, display: ${display}, visibility: ${visibility}, grid-column: ${gridColumn})`;
    } else {
      sidebarInfo = ' | Sidebar: NOT FOUND';
    }
    
    console.log(
      `%c📐 Viewport: ${width}px × ${height}px | Breakpoint: ${breakpoint}${sidebarInfo}`,
      'background: #1e293b; color: #60a5fa; padding: 8px 12px; border-radius: 4px; font-size: 14px; font-weight: bold;'
    );
  }

  /**
   * Get computed styles for an element
   */
  function getComputedDimensions(element) {
    const computed = window.getComputedStyle(element);
    const rect = element.getBoundingClientRect();

    return {
      width: rect.width,
      height: rect.height,
      offsetWidth: element.offsetWidth,
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      paddingLeft: parseFloat(computed.paddingLeft) || 0,
      paddingRight: parseFloat(computed.paddingRight) || 0,
      paddingTop: parseFloat(computed.paddingTop) || 0,
      paddingBottom: parseFloat(computed.paddingBottom) || 0,
      marginLeft: parseFloat(computed.marginLeft) || 0,
      marginRight: parseFloat(computed.marginRight) || 0,
      display: computed.display,
      flexDirection: computed.flexDirection,
      overflowX: computed.overflowX,
      overflowY: computed.overflowY,
    };
  }

  /**
   * Check for horizontal overflow
   */
  function checkOverflow(element, selector = '') {
    if (!DEBUG_CONFIG.logOverflows) return;

    const dims = getComputedDimensions(element);
    const overflow = dims.scrollWidth - dims.clientWidth;

    if (overflow > 0) {
      const identifier = selector || element.className || element.tagName;

      log(
        'OVERFLOW',
        `⚠️ Horizontal overflow detected: ${identifier}`,
        {
          overflow: `+${overflow}px`,
          scrollWidth: dims.scrollWidth,
          clientWidth: dims.clientWidth,
          offsetWidth: dims.offsetWidth,
          padding: `L:${dims.paddingLeft}px R:${dims.paddingRight}px`,
          element: element,
        },
        'color: #dc2626; font-weight: bold;'
      );

      if (DEBUG_CONFIG.highlightProblems) {
        element.style.outline = '3px solid red';
        element.setAttribute('data-overflow', overflow);
      }

      return true;
    }
    return false;
  }

  /**
   * Check padding issues (expected vs actual)
   */
  function checkPadding(element, selector = '', expectedPadding = null) {
    if (!DEBUG_CONFIG.logPaddingIssues) return;

    const dims = getComputedDimensions(element);
    const totalPadding = dims.paddingLeft + dims.paddingRight;

    if (expectedPadding && Math.abs(totalPadding - expectedPadding) > 1) {
      log(
        'PADDING',
        `❌ Padding mismatch: ${selector}`,
        {
          expected: `${expectedPadding}px`,
          actual: `${totalPadding}px (L:${dims.paddingLeft}px + R:${dims.paddingRight}px)`,
          difference: `${totalPadding - expectedPadding}px`,
          element: element,
        },
        'color: #f59e0b; font-weight: bold;'
      );

      if (DEBUG_CONFIG.highlightProblems) {
        element.style.outline = '3px dashed orange';
      }

      return true;
    }

    // Log actual padding for reference
    if (isMobile() && totalPadding > 0) {
      log(
        'PADDING',
        `${selector}: ${totalPadding}px total`,
        {
          left: dims.paddingLeft,
          right: dims.paddingRight,
          top: dims.paddingTop,
          bottom: dims.paddingBottom,
        },
        'color: #8b5cf6;'
      );
    }

    return false;
  }

  /**
   * Check flex layout issues
   */
  function checkFlexLayout(element, selector = '') {
    if (!DEBUG_CONFIG.logFlexIssues) return;

    const dims = getComputedDimensions(element);

    if (dims.display.includes('flex')) {
      const children = Array.from(element.children);
      const childWidths = children.map(child => child.offsetWidth);
      const totalChildWidth = childWidths.reduce((sum, w) => sum + w, 0);

      log(
        'FLEX',
        `${selector} (${dims.flexDirection})`,
        {
          containerWidth: dims.clientWidth,
          childrenCount: children.length,
          childWidths: childWidths,
          totalChildWidth: totalChildWidth,
          flexDirection: dims.flexDirection,
          willOverflow: totalChildWidth > dims.clientWidth,
        },
        'color: #10b981;'
      );

      // Check if flex-direction should be column on mobile
      if (isMobile() && dims.flexDirection === 'row' && totalChildWidth > dims.clientWidth) {
        log(
          'FLEX',
          `⚠️ ${selector} needs flex-direction: column on mobile`,
          {
            currentDirection: 'row',
            recommendedDirection: 'column',
            overflow: totalChildWidth - dims.clientWidth,
          },
          'color: #dc2626; font-weight: bold;'
        );

        if (DEBUG_CONFIG.highlightProblems) {
          element.style.outline = '3px dotted blue';
        }
      }
    }
  }

  /**
   * Check touch target sizes
   */
  function checkTouchTargets() {
    if (!DEBUG_CONFIG.logTouchTargets || !isMobile()) return;

    const interactiveSelectors = [
      'button',
      '.btn',
      'a.button',
      'input[type="submit"]',
      'input[type="button"]',
      '.btn-icon',
      '.icon-button',
      '.hamburger-btn',
    ];

    const interactiveElements = document.querySelectorAll(interactiveSelectors.join(','));

    interactiveElements.forEach(element => {
      const rect = element.getBoundingClientRect();
      const identifier = element.className || element.tagName;

      // Skip hidden elements (0×0px means display:none or visibility:hidden)
      if (rect.width === 0 && rect.height === 0) return;

      if (rect.width < DEBUG_CONFIG.minTouchTarget || rect.height < DEBUG_CONFIG.minTouchTarget) {
        log(
          'TOUCH',
          `⚠️ Touch target too small: ${identifier}`,
          {
            size: `${Math.round(rect.width)}×${Math.round(rect.height)}px`,
            minimum: `${DEBUG_CONFIG.minTouchTarget}×${DEBUG_CONFIG.minTouchTarget}px`,
            element: element,
          },
          'color: #dc2626; font-weight: bold;'
        );

        if (DEBUG_CONFIG.highlightProblems) {
          element.style.outline = '2px dashed red';
        }
      }
    });
  }

  /**
   * Check command center cards and other containers
   */
  function checkCards() {
    const cards = document.querySelectorAll('.mgmt-module-card, .cc-card, .sa-card, .card');

    cards.forEach((card, index) => {
      const identifier = card.className || `card[${index}]`;
      checkOverflow(card, identifier);
      checkPadding(card, identifier);
    });
  }

  /**
   * Check admin module grid
   */
  function checkAdminGrid() {
    const grid = document.querySelector('.mgmt-modules-grid');
    if (grid) {
      checkOverflow(grid, '.mgmt-modules-grid');
      checkPadding(grid, '.mgmt-modules-grid');
      checkFlexLayout(grid, '.mgmt-modules-grid');
    }
  }

  /**
   * Check tables for mobile overflow
   */
  function checkTables() {
    const tables = document.querySelectorAll('table, .data-table, .sa-table, .cc-table, .cc-analytics-table');

    tables.forEach((table, index) => {
      const container = table.closest('.data-table-container, .table-responsive, .cc-card');
      const selector = table.id ? `#${table.id}` : (table.className || `table[${index}]`);
      const tableWidth = table.scrollWidth;
      const tableOffsetWidth = table.offsetWidth;
      const viewportWidth = window.innerWidth;

      // Check if table itself is wider than its container
      const tableOverflow = tableWidth - tableOffsetWidth;

      if (tableOverflow > 5) {
        log(
          'TABLE',
          `⚠️ Table has horizontal scroll: ${selector}`,
          {
            scrollWidth: tableWidth,
            offsetWidth: tableOffsetWidth,
            overflow: `+${tableOverflow}px`,
            hasContainer: !!container,
            containerClass: container ? container.className : 'none',
            element: table,
          },
          'color: #f59e0b; font-weight: bold;'
        );

        if (DEBUG_CONFIG.highlightProblems) {
          table.style.outline = '3px solid orange';
        }
      }
    });
  }

  /**
   * Log viewport information
   */
  function logViewport() {
    if (!DEBUG_CONFIG.logViewportChanges) return;

    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const devicePixelRatio = window.devicePixelRatio || 1;
    const orientation = vw > vh ? 'landscape' : 'portrait';

    log(
      'VIEWPORT',
      `${vw}×${vh}px | ${orientation} | ${isMobile() ? 'MOBILE' : 'DESKTOP'}`,
      {
        width: vw,
        height: vh,
        devicePixelRatio: devicePixelRatio,
        orientation: orientation,
        breakpoint: isMobile() ? `≤${DEBUG_CONFIG.mobileBreakpoint}px` : `>${DEBUG_CONFIG.mobileBreakpoint}px`,
      },
      'color: #059669; font-weight: bold; font-size: 14px;'
    );
  }

  /**
   * Find the widest element causing body overflow
   */
  function findOverflowCulprit() {
    const culprits = [];
    const viewportWidth = window.innerWidth;

    // Check all direct children of body and their descendants
    const allElements = document.querySelectorAll('*');

    allElements.forEach(element => {
      const rect = element.getBoundingClientRect();
      const overflow = rect.right - viewportWidth;

      if (overflow > 5) { // More than 5px overflow
        culprits.push({
          element: element,
          identifier: element.id || element.className || element.tagName,
          width: rect.width,
          right: rect.right,
          overflow: overflow,
          scrollWidth: element.scrollWidth,
        });
      }
    });

    // Sort by overflow amount (worst first)
    culprits.sort((a, b) => b.overflow - a.overflow);

    return culprits;
  }

  /**
   * Run all checks
   */
  function runAllChecks() {
    log('DEBUG', '🔍 Running responsive debug checks...');

    logViewport();

    checkAdminGrid();
    checkCards();
    checkTables();

    if (isMobile()) {
      checkTouchTargets();
    }

    // Check for any horizontal page scroll
    const bodyOverflow = document.body.scrollWidth - document.body.clientWidth;
    if (bodyOverflow > 0) {
      log(
        'BODY',
        `⚠️ Page has horizontal scroll`,
        {
          overflow: `+${bodyOverflow}px`,
          bodyScrollWidth: document.body.scrollWidth,
          bodyClientWidth: document.body.clientWidth,
          windowInnerWidth: window.innerWidth,
        },
        'color: #dc2626; font-weight: bold; font-size: 16px;'
      );

      // Find and log the culprits
      const culprits = findOverflowCulprit();
      if (culprits.length > 0) {
        log(
          'CULPRIT',
          `🎯 Found ${culprits.length} elements causing overflow`,
          culprits.slice(0, 10).map(c => ({
            element: c.identifier,
            width: `${Math.round(c.width)}px`,
            overflow: `+${Math.round(c.overflow)}px`,
            ref: c.element,
          })),
          'color: #dc2626; font-weight: bold; font-size: 14px;'
        );

        // Highlight the worst offenders
        if (DEBUG_CONFIG.highlightProblems) {
          culprits.slice(0, 5).forEach((c, i) => {
            c.element.style.outline = `${3 - i * 0.5}px solid red`;
            c.element.setAttribute('data-overflow-culprit', c.overflow);
          });
        }
      }
    }

    log('DEBUG', '✅ Responsive debug checks complete');
  }

  /**
   * Handle viewport resize
   */
  function handleResize() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    // Only log if viewport size actually changed
    if (vw !== lastViewportWidth || vh !== lastViewportHeight) {
      lastViewportWidth = vw;
      lastViewportHeight = vh;

      log('RESIZE', `Viewport resized to ${vw}×${vh}px`);

      // Clear previous highlights
      if (DEBUG_CONFIG.highlightProblems) {
        document.querySelectorAll('[style*="outline"]').forEach(el => {
          el.style.outline = '';
          el.removeAttribute('data-overflow');
        });
      }

      // Re-run checks after resize
      setTimeout(runAllChecks, 100);
    }
  }

  /**
   * Initialize debug system
   */
  function init() {
    log('INIT', '🚀 Responsive Debug System initialized');

    // Run checks on DOM ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', runAllChecks);
    } else {
      runAllChecks();
    }

    // Run checks again after dynamic content loads
    setTimeout(() => {
      log('DEBUG', '🔄 Running delayed checks for dynamic content...');
      runAllChecks();
    }, 2000);

    // Watch for DOM mutations
    if (typeof MutationObserver !== 'undefined') {
      const observer = new MutationObserver((mutations) => {
        let shouldRecheck = false;
        mutations.forEach(mutation => {
          mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1) {
              const hasTable = node.tagName === 'TABLE' ||
                              (node.querySelector && node.querySelector('table, .mgmt-modules-grid'));
              if (hasTable) {
                shouldRecheck = true;
              }
            }
          });
        });

        if (shouldRecheck) {
          log('DEBUG', '🔄 New content detected, re-running checks...');
          setTimeout(runAllChecks, 500);
        }
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }

    // Run checks on resize (debounced)
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        handleResize();
        logViewportSize(); // Log size on resize
      }, 250);
    });

    // Run checks on orientation change
    window.addEventListener('orientationchange', () => {
      setTimeout(() => {
        runAllChecks();
        logViewportSize(); // Log size on orientation change
      }, 300);
    });

    // Log initial viewport size
    logViewportSize();

    // Expose debug functions globally
    window.ResponsiveDebug = {
      runChecks: runAllChecks,
      logSize: logViewportSize, // Expose viewport size logger
      checkElement: (selector) => {
        const element = typeof selector === 'string'
          ? document.querySelector(selector)
          : selector;

        if (element) {
          checkOverflow(element, selector);
          checkPadding(element, selector);
          checkFlexLayout(element, selector);
        } else {
          console.error('Element not found:', selector);
        }
      },
      enable: () => { DEBUG_CONFIG.enabled = true; },
      disable: () => { DEBUG_CONFIG.enabled = false; },
      config: DEBUG_CONFIG,
    };
  }

  // Auto-initialize
  init();

})();
