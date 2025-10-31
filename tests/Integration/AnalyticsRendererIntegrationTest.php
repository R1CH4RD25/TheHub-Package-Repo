<?php

namespace Tests\Integration;

use Hub\Modules\AnalyticsRenderer;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for AnalyticsRenderer
 * Tests validation, chart types, and configuration
 * 
 * NOTE: Uses database transactions - all changes are rolled back after each test
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\AnalyticsRenderer::class)]
class AnalyticsRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start transaction for test isolation - will be rolled back in tearDown
        TestDatabase::beginTransaction();
        
        $_GET = [];
    }
    
    protected function tearDown(): void
    {
        // Roll back all database changes made during test
        TestDatabase::rollBack();
        
        $_GET = [];
        parent::tearDown();
    }
    
    public function testAnalyticsValidationValid(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Count']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertTrue($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingCharts(): void
    {
        $config = ['entity' => 'users'];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingEntity(): void
    {
        $config = [
            'charts' => [
                ['type' => 'bar', 'title' => 'Test Chart']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationEmptyCharts(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => []
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingChartTitle(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar']  // Missing title
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testGetConfig(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'line', 'title' => 'User Growth']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $retrievedConfig = $analytics->getConfig();
        
        $this->assertIsArray($retrievedConfig);
        $this->assertEquals('users', $retrievedConfig['entity']);
        $this->assertCount(1, $retrievedConfig['charts']);
    }
    
    public function testRenderMethodReturnsHtml(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Statistics']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $html = $analytics->render();
        
        $this->assertIsString($html);
        $this->assertStringContainsString('chart', strtolower($html));
    }
    
    public function testDifferentChartTypes(): void
    {
        $chartTypes = ['bar', 'line', 'pie', 'doughnut'];
        
        foreach ($chartTypes as $type) {
            $config = [
                'entity' => 'users',
                'charts' => [
                    ['type' => $type, 'title' => ucfirst($type) . ' Chart']
                ]
            ];
            
            $analytics = new AnalyticsRenderer($config);
            $this->assertTrue($analytics->validate());
            $html = $analytics->render();
            
            $this->assertIsString($html);
            $this->assertNotEmpty($html);
        }
    }
    
    public function testConfigurationWithMultipleCharts(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Count'],
                ['type' => 'line', 'title' => 'Growth Trend'],
                ['type' => 'pie', 'title' => 'Distribution']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertTrue($analytics->validate());
        
        $retrievedConfig = $analytics->getConfig();
        $this->assertCount(3, $retrievedConfig['charts']);
    }

    public function testRenderWithFilters(): void
    {
        $config = [
            'entity' => 'users',
            'filters' => [
                ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => ['admin', 'user']],
                ['name' => 'status', 'label' => 'Status', 'type' => 'text']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Test Chart']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Role', $html);
        $this->assertStringContainsString('Status', $html);
    }

    public function testRenderFiltersWithDateType(): void
    {
        $config = [
            'entity' => 'orders',
            'filters' => [
                ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date']
            ],
            'charts' => [['type' => 'line', 'title' => 'Sales']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('Start Date', $html);
    }

    public function testRenderFiltersWithSelectOptions(): void
    {
        $config = [
            'entity' => 'products',
            'filters' => [
                [
                    'name' => 'category',
                    'label' => 'Category',
                    'type' => 'select',
                    'options' => [
                        ['value' => '1', 'label' => 'Electronics'],
                        ['value' => '2', 'label' => 'Books']
                    ]
                ]
            ],
            'charts' => [['type' => 'pie', 'title' => 'Categories']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Electronics', $html);
        $this->assertStringContainsString('Books', $html);
    }

    public function testLoadChartDataWithQuery(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_data (id INT, value INT)");
        $db->exec("INSERT INTO test_data VALUES (1, 100), (2, 200)");

        $config = [
            'entity' => 'test_data',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Data Chart',
                    'query' => 'SELECT id as label, value FROM test_data'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Data Chart', $html);
        $this->assertStringContainsString('canvas', $html);
    }

    public function testRenderChartWithCustomColors(): void
    {
        $config = [
            'entity' => 'sales',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Sales',
                    'colors' => ['#FF6384', '#36A2EB', '#FFCE56']
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Sales', $html);
    }

    public function testRenderChartWithCustomDimensions(): void
    {
        $config = [
            'entity' => 'metrics',
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Metrics',
                    'width' => 800,
                    'height' => 400
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('canvas', $html);
        $this->assertStringContainsString('Metrics', $html);
    }

    public function testRenderScripts(): void
    {
        $config = [
            'entity' => 'data',
            'charts' => [['type' => 'bar', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('chart', $html);
    }

    public function testCacheBehavior(): void
    {
        $config = [
            'entity' => 'users',
            'cacheTtl' => 60,
            'charts' => [['type' => 'bar', 'title' => 'Users']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html1 = $renderer->render();
        $html2 = $renderer->render();

        $this->assertStringContainsString('Users', $html1);
        $this->assertStringContainsString('Users', $html2);
    }

    public function testLoadDataWithAggregation(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_agg (category VARCHAR(50), amount INT)");
        $db->exec("INSERT INTO test_agg VALUES ('A', 10), ('B', 20), ('A', 15)");

        $config = [
            'entity' => 'test_agg',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Aggregated',
                    'query' => 'SELECT category as label, SUM(amount) as value FROM test_agg GROUP BY category'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Aggregated', $html);
    }

    public function testLoadDataWithJoins(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_a (id INT, name VARCHAR(50))");
        $db->exec("CREATE TEMPORARY TABLE test_b (id INT, a_id INT, value INT)");
        $db->exec("INSERT INTO test_a VALUES (1, 'Item1'), (2, 'Item2')");
        $db->exec("INSERT INTO test_b VALUES (1, 1, 100), (2, 2, 200)");

        $config = [
            'entity' => 'test_a',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Joined Data',
                    'query' => 'SELECT a.name as label, b.value FROM test_a a JOIN test_b b ON a.id = b.a_id'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Joined Data', $html);
    }

    public function testHandleMethodReadOnly(): void
    {
        $config = [
            'entity' => 'data',
            'charts' => [['type' => 'bar', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->handle(['action' => 'test']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('read-only', $result['message']);
    }

    public function testGetChartColorForAllTypes(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [
                ['type' => 'bar', 'title' => 'Bar'],
                ['type' => 'line', 'title' => 'Line'],
                ['type' => 'pie', 'title' => 'Pie'],
                ['type' => 'doughnut', 'title' => 'Doughnut'],
                ['type' => 'radar', 'title' => 'Radar'],
                ['type' => 'polarArea', 'title' => 'Polar']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Bar', $html);
        $this->assertStringContainsString('Line', $html);
        $this->assertStringContainsString('Pie', $html);
    }

    public function testRenderWithLegendOptions(): void
    {
        $config = [
            'entity' => 'sales',
            'charts' => [
                [
                    'type' => 'pie',
                    'title' => 'Sales',
                    'showLegend' => true,
                    'legendPosition' => 'bottom'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Sales', $html);
    }

    public function testRenderWithStackedBars(): void
    {
        $config = [
            'entity' => 'metrics',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Stacked Metrics',
                    'stacked' => true
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Stacked Metrics', $html);
    }

    public function testEmptyDataHandling(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_empty (id INT)");

        $config = [
            'entity' => 'test_empty',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Empty Chart',
                    'query' => 'SELECT id as label, 0 as value FROM test_empty'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Empty Chart', $html);
    }

    public function testRenderWithTooltipOptions(): void
    {
        $config = [
            'entity' => 'data',
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Tooltip Test',
                    'showTooltip' => true
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Tooltip Test', $html);
    }

    public function testLoadChartDataWithLimit(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_limit (id INT, value INT)");
        $db->exec("INSERT INTO test_limit VALUES (1,10), (2,20), (3,30), (4,40), (5,50)");

        $config = [
            'entity' => 'test_limit',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Limited Data',
                    'query' => 'SELECT id as label, value FROM test_limit LIMIT 3'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Limited Data', $html);
    }

    public function testLoadChartDataWithOrderBy(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_order (name VARCHAR(50), score INT)");
        $db->exec("INSERT INTO test_order VALUES ('Alice', 95), ('Bob', 87), ('Charlie', 92)");

        $config = [
            'entity' => 'test_order',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Scores',
                    'query' => 'SELECT name as label, score as value FROM test_order ORDER BY score DESC'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Scores', $html);
    }

    public function testValidationInvalidChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [
                ['type' => 'invalid_type', 'title' => 'Test']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Invalid analytics configuration', $html);
    }

    public function testRenderFiltersWithNumberType(): void
    {
        $config = [
            'entity' => 'products',
            'filters' => [
                ['name' => 'min_price', 'label' => 'Min Price', 'type' => 'number']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Products']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Min Price', $html);
    }


    public function testRenderFiltersWithTextInput(): void
    {
        $config = [
            'entity' => 'products',
            'filters' => [
                ['name' => 'search', 'label' => 'Search', 'type' => 'text']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Products']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Search', $html);
        $this->assertStringContainsString('type="text"', $html);
    }

    public function testRenderMultipleFilters(): void
    {
        $config = [
            'entity' => 'orders',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending', 'completed']],
                ['name' => 'date_from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'amount_min', 'label' => 'Min Amount', 'type' => 'number']
            ],
            'charts' => [['type' => 'line', 'title' => 'Orders']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Status', $html);
        $this->assertStringContainsString('From', $html);
        $this->assertStringContainsString('Min Amount', $html);
    }

    public function testCacheSetAndGet(): void
    {
        $config = [
            'entity' => 'test_cache',
            'cacheTtl' => 120,
            'charts' => [['type' => 'bar', 'title' => 'Cached']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Cached', $html);
    }

    public function testRenderChartWithAllOptions(): void
    {
        $config = [
            'entity' => 'comprehensive',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Comprehensive Chart',
                    'colors' => ['#FF0000'],
                    'width' => 600,
                    'height' => 300,
                    'showLegend' => true,
                    'legendPosition' => 'right',
                    'stacked' => true,
                    'showTooltip' => true
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Comprehensive Chart', $html);
    }

    public function testLoadChartDataWithComplexQuery(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_complex (
            id INT,
            category VARCHAR(50),
            subcategory VARCHAR(50),
            value INT
        )");
        $db->exec("INSERT INTO test_complex VALUES 
            (1, 'A', 'A1', 10),
            (2, 'A', 'A2', 20),
            (3, 'B', 'B1', 15)");

        $config = [
            'entity' => 'test_complex',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Complex Data',
                    'query' => 'SELECT category as label, SUM(value) as value FROM test_complex WHERE value > 5 GROUP BY category HAVING SUM(value) > 10 ORDER BY value DESC'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Complex Data', $html);
    }

    public function testRenderWithFiltersAndGetParams(): void
    {
        $_GET['test_filter'] = 'test_value';
        
        $config = [
            'entity' => 'filtered',
            'filters' => [
                ['name' => 'test_filter', 'label' => 'Test Filter', 'type' => 'text']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Filtered']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('test_value', $html);
        
        unset($_GET['test_filter']);
    }

    public function testValidationMissingType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [
                ['title' => 'No Type']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Invalid analytics configuration', $html);
    }

    public function testRenderScriptsWithChartData(): void
    {
        $config = [
            'entity' => 'script_test',
            'charts' => [
                ['type' => 'line', 'title' => 'Script Test']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function testGetChartColorGeneration(): void
    {
        $config = [
            'entity' => 'colors',
            'charts' => [
                ['type' => 'pie', 'title' => 'C1'],
                ['type' => 'doughnut', 'title' => 'C2'],
                ['type' => 'radar', 'title' => 'C3'],
                ['type' => 'polarArea', 'title' => 'C4']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('C1', $html);
        $this->assertStringContainsString('C2', $html);
        $this->assertStringContainsString('C3', $html);
        $this->assertStringContainsString('C4', $html);
    }


    public function testRenderFiltersWithSimpleStringOptions(): void
    {
        $config = [
            'entity' => 'simple',
            'filters' => [
                [
                    'name' => 'category',
                    'label' => 'Category',
                    'type' => 'select',
                    'options' => ['Option1', 'Option2', 'Option3']
                ]
            ],
            'charts' => [['type' => 'bar', 'title' => 'Simple']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Option1', $html);
        $this->assertStringContainsString('Option2', $html);
    }

    public function testRenderFiltersSelectWithSelectedValue(): void
    {
        $_GET['status'] = 'active';
        
        $config = [
            'entity' => 'items',
            'filters' => [
                [
                    'name' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['active', 'inactive']
                ]
            ],
            'charts' => [['type' => 'bar', 'title' => 'Items']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('selected', $html);
        
        unset($_GET['status']);
    }

    public function testRenderChartWithQueryAndData(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_render (name VARCHAR(50), count INT)");
        $db->exec("INSERT INTO test_render VALUES ('Item1', 5), ('Item2', 10), ('Item3', 15)");

        $config = [
            'entity' => 'test_render',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Render Test',
                    'query' => 'SELECT name as label, count as value FROM test_render'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Render Test', $html);
        $this->assertStringContainsString('canvas', $html);
    }

    public function testLoadDataWithWhereClause(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_where (id INT, status VARCHAR(20), value INT)");
        $db->exec("INSERT INTO test_where VALUES (1, 'active', 100), (2, 'inactive', 50), (3, 'active', 75)");

        $config = [
            'entity' => 'test_where',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Filtered',
                    'query' => 'SELECT id as label, value FROM test_where WHERE status = "active"'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Filtered', $html);
    }

    public function testRenderWithNoFilters(): void
    {
        $config = [
            'entity' => 'no_filters',
            'charts' => [['type' => 'bar', 'title' => 'No Filters']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('No Filters', $html);
        $this->assertStringNotContainsString('form-label', $html);
    }

    public function testRenderMultipleChartsInRow(): void
    {
        $config = [
            'entity' => 'multi',
            'charts' => [
                ['type' => 'bar', 'title' => 'Chart 1'],
                ['type' => 'line', 'title' => 'Chart 2'],
                ['type' => 'pie', 'title' => 'Chart 3']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Chart 1', $html);
        $this->assertStringContainsString('Chart 2', $html);
        $this->assertStringContainsString('Chart 3', $html);
        $this->assertStringContainsString('row', $html);
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'entity' => 'config_test',
            'cacheTtl' => 90,
            'charts' => [['type' => 'bar', 'title' => 'Config']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $returnedConfig = $renderer->getConfig();

        $this->assertIsArray($returnedConfig);
        $this->assertEquals('config_test', $returnedConfig['entity']);
        $this->assertEquals(90, $returnedConfig['cacheTtl']);
    }

    public function testRenderFiltersFormSubmission(): void
    {
        $config = [
            'entity' => 'form_test',
            'filters' => [
                ['name' => 'search', 'label' => 'Search', 'type' => 'text']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Form Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="GET"', $html);
    }

    public function testRenderChartCanvas(): void
    {
        $config = [
            'entity' => 'canvas_test',
            'charts' => [
                ['type' => 'line', 'title' => 'Canvas Test', 'id' => 0]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Canvas Test', $html);
        $this->assertStringContainsString('<script', $html);
    }

    public function testLoadChartDataWithHaving(): void
    {
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_having (category VARCHAR(50), amount INT)");
        $db->exec("INSERT INTO test_having VALUES ('A', 5), ('A', 10), ('B', 3), ('C', 20)");

        $config = [
            'entity' => 'test_having',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Having Clause',
                    'query' => 'SELECT category as label, SUM(amount) as value FROM test_having GROUP BY category HAVING SUM(amount) > 10'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Having Clause', $html);
    }


    public function testRenderFiltersSubmitAndResetButtons(): void
    {
        $config = [
            'entity' => 'test',
            'filters' => [
                ['name' => 'q', 'label' => 'Query', 'type' => 'text']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Apply Filters', $html);
        $this->assertStringContainsString('Reset', $html);
        $this->assertStringContainsString('btn-primary', $html);
        $this->assertStringContainsString('btn-secondary', $html);
    }

    public function testRenderChartExportButton(): void
    {
        $config = [
            'entity' => 'export_test',
            'charts' => [
                ['type' => 'bar', 'title' => 'Exportable Chart', 'id' => 0]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString('export-chart', $html);
    }

    public function testRenderChartCardStructure(): void
    {
        $config = [
            'entity' => 'card_test',
            'charts' => [
                ['type' => 'line', 'title' => 'Card Test']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('card', $html);
        $this->assertStringContainsString('card-header', $html);
        $this->assertStringContainsString('card-body', $html);
    }

    public function testRenderChartWithColSize(): void
    {
        $config = [
            'entity' => 'colsize',
            'charts' => [
                ['type' => 'bar', 'title' => 'Full Width', 'width' => 'col-md-12']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('col-md-12', $html);
    }

    public function testLoadChartDataException(): void
    {
        $config = [
            'entity' => 'nonexistent_table',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Error Test',
                    'query' => 'SELECT * FROM nonexistent_table_xyz'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        // Should handle error gracefully
        $this->assertStringContainsString('Error Test', $html);
    }

    public function testRenderWithNumberFilter(): void
    {
        $config = [
            'entity' => 'number_filter',
            'filters' => [
                ['name' => 'min_value', 'label' => 'Min Value', 'type' => 'number']
            ],
            'charts' => [['type' => 'bar', 'title' => 'Numbers']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('type="text"', $html); // Falls back to text
        $this->assertStringContainsString('Min Value', $html);
    }

    public function testRenderChartDefaultTitle(): void
    {
        $config = [
            'entity' => 'default',
            'charts' => [
                ['type' => 'bar'] // No title provided
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        // Should have validation error since title is required
        $this->assertStringContainsString('Invalid analytics configuration', $html);
    }


    public function testLoadChartDataWithXAxisYAxis(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_analytics (
            id INT PRIMARY KEY,
            tenant_id VARCHAR(50),
            created_at DATE,
            value INT
        )");
        $db->exec("INSERT INTO test_analytics VALUES 
            (1, 'test-tenant', '2024-01-01', 100),
            (2, 'test-tenant', '2024-01-02', 200)");

        $config = [
            'entity' => 'test_analytics',
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Analytics Data',
                    'xAxis' => 'created_at',
                    'yAxis' => 'SUM(value)'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Analytics Data', $html);
        
        unset($_SESSION['tenant_id']);
    }

    public function testLoadChartDataWithGroupByMonth(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_monthly (
            id INT,
            tenant_id VARCHAR(50),
            created_at DATE,
            amount DECIMAL(10,2)
        )");
        $db->exec("INSERT INTO test_monthly VALUES 
            (1, 'test-tenant', '2024-01-15', 100.00),
            (2, 'test-tenant', '2024-01-20', 150.00),
            (3, 'test-tenant', '2024-02-10', 200.00)");

        $config = [
            'entity' => 'test_monthly',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Monthly Data',
                    'xAxis' => 'created_at',
                    'yAxis' => 'SUM(amount)',
                    'groupBy' => 'MONTH'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Monthly Data', $html);
        
        unset($_SESSION['tenant_id']);
    }

    public function testLoadChartDataWithGroupByYear(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_yearly (
            id INT,
            tenant_id VARCHAR(50),
            created_at DATE,
            revenue DECIMAL(10,2)
        )");
        $db->exec("INSERT INTO test_yearly VALUES 
            (1, 'test-tenant', '2023-06-01', 1000.00),
            (2, 'test-tenant', '2024-03-15', 1500.00)");

        $config = [
            'entity' => 'test_yearly',
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Yearly Revenue',
                    'xAxis' => 'created_at',
                    'yAxis' => 'SUM(revenue)',
                    'groupBy' => 'YEAR'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Yearly Revenue', $html);
        
        unset($_SESSION['tenant_id']);
    }

    public function testLoadChartDataWithGroupByDay(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_daily (
            id INT,
            tenant_id VARCHAR(50),
            log_date DATE,
            count INT
        )");
        $db->exec("INSERT INTO test_daily VALUES 
            (1, 'test-tenant', '2024-01-01', 50),
            (2, 'test-tenant', '2024-01-02', 75),
            (3, 'test-tenant', '2024-01-03', 60)");

        $config = [
            'entity' => 'test_daily',
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Daily Counts',
                    'xAxis' => 'log_date',
                    'yAxis' => 'SUM(count)',
                    'groupBy' => 'DAY'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Daily Counts', $html);
        
        unset($_SESSION['tenant_id']);
    }

    public function testLoadChartDataWithFilterApplied(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        $_GET['status'] = 'active';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_filtered (
            id INT,
            tenant_id VARCHAR(50),
            status VARCHAR(20),
            created_at DATE,
            value INT
        )");
        $db->exec("INSERT INTO test_filtered VALUES 
            (1, 'test-tenant', 'active', '2024-01-01', 100),
            (2, 'test-tenant', 'inactive', '2024-01-02', 50),
            (3, 'test-tenant', 'active', '2024-01-03', 150)");

        $config = [
            'entity' => 'test_filtered',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive']]
            ],
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Filtered Data',
                    'xAxis' => 'created_at',
                    'yAxis' => 'SUM(value)'
                ]
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Filtered Data', $html);
        
        unset($_GET['status']);
        unset($_SESSION['tenant_id']);
    }

    public function testGetChartColorForDifferentTypes(): void
    {
        $_SESSION['tenant_id'] = 'test-tenant';
        
        $db = TestDatabase::getInstance()->getConnection();
        $db->exec("CREATE TEMPORARY TABLE test_colors (
            id INT,
            tenant_id VARCHAR(50),
            created_at DATE,
            value INT
        )");
        $db->exec("INSERT INTO test_colors VALUES 
            (1, 'test-tenant', '2024-01-01', 10)");

        $config = [
            'entity' => 'test_colors',
            'charts' => [
                ['type' => 'pie', 'title' => 'Pie', 'xAxis' => 'created_at', 'yAxis' => 'value'],
                ['type' => 'doughnut', 'title' => 'Doughnut', 'xAxis' => 'created_at', 'yAxis' => 'value'],
                ['type' => 'radar', 'title' => 'Radar', 'xAxis' => 'created_at', 'yAxis' => 'value']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Pie', $html);
        $this->assertStringContainsString('Doughnut', $html);
        $this->assertStringContainsString('Radar', $html);
        
        unset($_SESSION['tenant_id']);
    }

}
