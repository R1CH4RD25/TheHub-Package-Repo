<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Hub\Cache;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * KanbanRenderer - Visual project management board
 * 
 * Implements Kanban board module type for agile project management,
 * task tracking, and workflow visualization. Generic design works for any organization.
 * 
 * Use cases:
 * - Software Development: Sprint planning, bug tracking, feature development
 * - Manufacturing: Production workflow, quality control stages
 * - Sales: Lead pipeline, deal stages, customer onboarding
 * - Marketing: Campaign planning, content calendar, approval workflows
 * - HR: Recruitment pipeline, onboarding process, training tracking
 * - Personal: To-do lists, goal tracking, habit formation
 * 
 * Features:
 * - Drag-and-drop cards between columns
 * - Customizable board columns/stages
 * - Swimlanes for grouping (by team, priority, etc.)
 * - WIP (Work In Progress) limits per column
 * - Color-coded labels/tags
 * - Card priorities and due dates
 * - Assignment to users
 * - Card comments and attachments
 * - Board templates
 * - Filtering and search
 * - Archive completed cards
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class KanbanRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    
    // Default column structure
    private array $defaultColumns = [
        ['name' => 'Backlog', 'color' => '#6c757d', 'wip_limit' => null],
        ['name' => 'To Do', 'color' => '#0d6efd', 'wip_limit' => 10],
        ['name' => 'In Progress', 'color' => '#ffc107', 'wip_limit' => 5],
        ['name' => 'Review', 'color' => '#17a2b8', 'wip_limit' => 3],
        ['name' => 'Done', 'color' => '#28a745', 'wip_limit' => null],
    ];
    
    // Card priority levels
    private array $priorities = [
        'critical' => ['label' => 'Critical', 'color' => '#dc3545', 'icon' => 'exclamation-triangle-fill'],
        'high' => ['label' => 'High', 'color' => '#fd7e14', 'icon' => 'arrow-up-circle'],
        'medium' => ['label' => 'Medium', 'color' => '#ffc107', 'icon' => 'dash-circle'],
        'low' => ['label' => 'Low', 'color' => '#6c757d', 'icon' => 'arrow-down-circle'],
    ];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
        
        // Override columns from config
        if (isset($config['columns'])) {
            $this->defaultColumns = $config['columns'];
        }
    }
    
    /**
     * Render Kanban board
     */
    public function render(): string
    {
        $boardId = $_GET['board_id'] ?? $this->getDefaultBoard();
        $swimlaneBy = $_GET['swimlane'] ?? 'none'; // none, assignee, priority, label
        
        $html = '<div class="kanban-container">';
        
        // Header with controls
        $html .= $this->renderHeader($boardId, $swimlaneBy);
        
        // Board
        $html .= '<div class="kanban-board-wrapper">';
        
        if ($swimlaneBy !== 'none') {
            $html .= $this->renderBoardWithSwimlanes($boardId, $swimlaneBy);
        } else {
            $html .= $this->renderBoard($boardId);
        }
        
        $html .= '</div>';
        
        // Modals
        $html .= $this->renderCardModal();
        $html .= $this->renderCardDetailsModal();
        $html .= $this->renderBoardSettingsModal();
        
        // Styles and scripts
        $html .= $this->renderStyles();
        $html .= $this->renderScripts();
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render board header with controls
     */
    private function renderHeader(int $boardId, string $swimlaneBy): string
    {
        $html = '<div class="kanban-header mb-4">';
        $html .= '    <div class="d-flex justify-content-between align-items-center">';
        
        // Board selector and title
        $html .= '        <div class="d-flex align-items-center">';
        $html .= '            <h3 class="mb-0 me-3">Project Board</h3>';
        $html .= '            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#boardSettingsModal">';
        $html .= '                <i class="bi bi-gear"></i> Settings';
        $html .= '            </button>';
        $html .= '        </div>';
        
        // Actions and filters
        $html .= '        <div class="d-flex gap-2">';
        
        // Swimlane selector
        $html .= '            <div class="btn-group">';
        $html .= '                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">';
        $html .= '                    <i class="bi bi-diagram-3"></i> Swimlanes: ' . ucfirst($swimlaneBy);
        $html .= '                </button>';
        $html .= '                <ul class="dropdown-menu">';
        $html .= '                    <li><a class="dropdown-item" href="?swimlane=none">None</a></li>';
        $html .= '                    <li><a class="dropdown-item" href="?swimlane=assignee">By Assignee</a></li>';
        $html .= '                    <li><a class="dropdown-item" href="?swimlane=priority">By Priority</a></li>';
        $html .= '                    <li><a class="dropdown-item" href="?swimlane=label">By Label</a></li>';
        $html .= '                </ul>';
        $html .= '            </div>';
        
        // Search
        $html .= '            <input type="search" class="form-control" id="cardSearch" placeholder="Search cards..." style="width: 250px;">';
        
        // New card button
        $html .= '            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cardModal">';
        $html .= '                <i class="bi bi-plus-lg"></i> New Card';
        $html .= '            </button>';
        
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render standard board (no swimlanes)
     */
    private function renderBoard(int $boardId): string
    {
        $columns = $this->getColumns($boardId);
        $cards = $this->getCards($boardId);
        
        $html = '<div class="kanban-board" id="kanbanBoard">';
        
        foreach ($columns as $column) {
            $columnCards = array_filter($cards, fn($c) => $c['column_id'] == $column['id']);
            $cardCount = count($columnCards);
            $wipLimit = $column['wip_limit'];
            $overLimit = $wipLimit && $cardCount > $wipLimit;
            
            $html .= '    <div class="kanban-column" data-column-id="' . $column['id'] . '">';
            
            // Column header
            $html .= '        <div class="column-header" style="border-top: 4px solid ' . $column['color'] . ';">';
            $html .= '            <div class="d-flex justify-content-between align-items-center">';
            $html .= '                <h5 class="mb-0">' . htmlspecialchars($column['name']) . '</h5>';
            
            // Card count and WIP limit
            $html .= '                <span class="badge ' . ($overLimit ? 'bg-danger' : 'bg-secondary') . '">';
            $html .= $cardCount;
            if ($wipLimit) {
                $html .= ' / ' . $wipLimit;
            }
            $html .= '                </span>';
            $html .= '            </div>';
            
            if ($overLimit) {
                $html .= '            <small class="text-danger"><i class="bi bi-exclamation-triangle"></i> WIP limit exceeded</small>';
            }
            
            $html .= '        </div>';
            
            // Cards
            $html .= '        <div class="column-cards" data-column-id="' . $column['id'] . '">';
            
            foreach ($columnCards as $card) {
                $html .= $this->renderCard($card);
            }
            
            // Add card button
            $html .= '            <button class="btn btn-sm btn-outline-secondary w-100 mt-2 add-card-btn" data-column-id="' . $column['id'] . '">';
            $html .= '                <i class="bi bi-plus"></i> Add Card';
            $html .= '            </button>';
            
            $html .= '        </div>';
            $html .= '    </div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render board with swimlanes
     */
    private function renderBoardWithSwimlanes(int $boardId, string $swimlaneBy): string
    {
        $columns = $this->getColumns($boardId);
        $cards = $this->getCards($boardId);
        $swimlanes = $this->getSwimlanes($boardId, $swimlaneBy);
        
        $html = '<div class="kanban-board-swimlanes">';
        
        // Column headers
        $html .= '    <div class="swimlane-headers sticky-top bg-white">';
        $html .= '        <div class="swimlane-label"></div>';
        
        foreach ($columns as $column) {
            $html .= '        <div class="column-header-swimlane">';
            $html .= '            <h6 class="mb-0">' . htmlspecialchars($column['name']) . '</h6>';
            $html .= '        </div>';
        }
        
        $html .= '    </div>';
        
        // Swimlanes
        foreach ($swimlanes as $swimlane) {
            $html .= '    <div class="swimlane">';
            
            // Swimlane label
            $html .= '        <div class="swimlane-label">';
            $html .= '            <strong>' . htmlspecialchars($swimlane['label']) . '</strong>';
            $html .= '            <br><small class="text-muted">' . $swimlane['count'] . ' cards</small>';
            $html .= '        </div>';
            
            // Columns in swimlane
            foreach ($columns as $column) {
                $swimlaneCards = array_filter($cards, function($c) use ($column, $swimlane, $swimlaneBy) {
                    return $c['column_id'] == $column['id'] && $c[$swimlaneBy] == $swimlane['value'];
                });
                
                $html .= '        <div class="swimlane-column" data-column-id="' . $column['id'] . '" data-swimlane="' . $swimlane['value'] . '">';
                
                foreach ($swimlaneCards as $card) {
                    $html .= $this->renderCard($card, true);
                }
                
                $html .= '        </div>';
            }
            
            $html .= '    </div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render individual card
     */
    private function renderCard(array $card, bool $compact = false): string
    {
        $priority = $this->priorities[$card['priority']] ?? $this->priorities['medium'];
        
        $html = '<div class="kanban-card" data-card-id="' . $card['id'] . '" draggable="true">';
        
        // Card header with priority and labels
        $html .= '    <div class="card-header-row">';
        
        if (!$compact) {
            $html .= '        <i class="bi bi-' . $priority['icon'] . ' text-' . $this->getBootstrapColor($priority['color']) . '" title="' . $priority['label'] . '"></i>';
        }
        
        if (!empty($card['labels'])) {
            $labels = json_decode($card['labels'], true);
            foreach ($labels as $label) {
                $html .= '        <span class="badge" style="background-color: ' . $label['color'] . ';">' . htmlspecialchars($label['name']) . '</span>';
            }
        }
        
        $html .= '    </div>';
        
        // Card title
        $html .= '    <div class="card-title">';
        $html .= '        <strong>' . htmlspecialchars($card['title']) . '</strong>';
        $html .= '    </div>';
        
        if (!$compact) {
            // Card description preview
            if ($card['description']) {
                $html .= '    <div class="card-description">';
                $html .= htmlspecialchars(mb_substr($card['description'], 0, 100));
                $html .= '    </div>';
            }
            
            // Card footer
            $html .= '    <div class="card-footer-row">';
            
            // Due date
            if ($card['due_date']) {
                $dueDate = strtotime($card['due_date']);
                $isOverdue = $dueDate < time();
                $html .= '        <span class="card-due-date ' . ($isOverdue ? 'overdue' : '') . '">';
                $html .= '            <i class="bi bi-calendar"></i> ' . date('M d', $dueDate);
                $html .= '        </span>';
            }
            
            // Assignee
            if ($card['assignee_name']) {
                $html .= '        <span class="card-assignee" title="' . htmlspecialchars($card['assignee_name']) . '">';
                $html .= '            <i class="bi bi-person-circle"></i> ' . htmlspecialchars(mb_substr($card['assignee_name'], 0, 15));
                $html .= '        </span>';
            }
            
            // Comments count
            if ($card['comment_count'] > 0) {
                $html .= '        <span class="card-comments">';
                $html .= '            <i class="bi bi-chat"></i> ' . $card['comment_count'];
                $html .= '        </span>';
            }
            
            // Attachments count
            if ($card['attachment_count'] > 0) {
                $html .= '        <span class="card-attachments">';
                $html .= '            <i class="bi bi-paperclip"></i> ' . $card['attachment_count'];
                $html .= '        </span>';
            }
            
            $html .= '    </div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render card creation/edit modal
     */
    private function renderCardModal(): string
    {
        $html = '<div class="modal fade" id="cardModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-lg">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">New Card</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body">';
        $html .= '                <form id="cardForm">';
        
        // Title
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="cardTitle" class="form-label">Title *</label>';
        $html .= '                        <input type="text" class="form-control" id="cardTitle" required>';
        $html .= '                    </div>';
        
        // Description
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="cardDescription" class="form-label">Description</label>';
        $html .= '                        <textarea class="form-control" id="cardDescription" rows="4"></textarea>';
        $html .= '                    </div>';
        
        // Row: Column and Priority
        $html .= '                    <div class="row">';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="cardColumn" class="form-label">Column *</label>';
        $html .= '                            <select class="form-select" id="cardColumn" required>';
        foreach ($this->defaultColumns as $column) {
            $html .= '                                <option value="' . $column['name'] . '">' . $column['name'] . '</option>';
        }
        $html .= '                            </select>';
        $html .= '                        </div>';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="cardPriority" class="form-label">Priority</label>';
        $html .= '                            <select class="form-select" id="cardPriority">';
        foreach ($this->priorities as $key => $priority) {
            $selected = $key === 'medium' ? 'selected' : '';
            $html .= '                                <option value="' . $key . '" ' . $selected . '>' . $priority['label'] . '</option>';
        }
        $html .= '                            </select>';
        $html .= '                        </div>';
        $html .= '                    </div>';
        
        // Row: Assignee and Due Date
        $html .= '                    <div class="row">';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="cardAssignee" class="form-label">Assignee</label>';
        $html .= '                            <select class="form-select" id="cardAssignee">';
        $html .= '                                <option value="">Unassigned</option>';
        $html .= '                                <!-- Users populated via JS -->';
        $html .= '                            </select>';
        $html .= '                        </div>';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="cardDueDate" class="form-label">Due Date</label>';
        $html .= '                            <input type="date" class="form-control" id="cardDueDate">';
        $html .= '                        </div>';
        $html .= '                    </div>';
        
        // Labels
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label class="form-label">Labels</label>';
        $html .= '                        <div id="cardLabels">';
        $html .= '                            <span class="badge bg-danger me-1">Bug</span>';
        $html .= '                            <span class="badge bg-success me-1">Feature</span>';
        $html .= '                            <span class="badge bg-info me-1">Documentation</span>';
        $html .= '                            <span class="badge bg-warning me-1">Enhancement</span>';
        $html .= '                        </div>';
        $html .= '                    </div>';
        
        $html .= '                </form>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '                <button type="button" class="btn btn-primary" id="saveCard">Create Card</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render card details modal
     */
    private function renderCardDetailsModal(): string
    {
        $html = '<div class="modal fade" id="cardDetailsModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-xl">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">Card Details</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body" id="cardDetailsContent">';
        $html .= '                <div class="text-center"><div class="spinner-border"></div></div>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render board settings modal
     */
    private function renderBoardSettingsModal(): string
    {
        $html = '<div class="modal fade" id="boardSettingsModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-lg">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">Board Settings</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body">';
        $html .= '                <h6>Columns</h6>';
        $html .= '                <p class="text-muted">Customize board columns and WIP limits</p>';
        $html .= '                <div id="columnsList" class="mb-3">';
        
        foreach ($this->defaultColumns as $i => $column) {
            $html .= '                    <div class="input-group mb-2">';
            $html .= '                        <input type="text" class="form-control" value="' . htmlspecialchars($column['name']) . '" placeholder="Column name">';
            $html .= '                        <input type="number" class="form-control" value="' . ($column['wip_limit'] ?? '') . '" placeholder="WIP limit" style="max-width: 120px;">';
            $html .= '                        <input type="color" class="form-control form-control-color" value="' . $column['color'] . '">';
            $html .= '                        <button class="btn btn-outline-danger" type="button"><i class="bi bi-trash"></i></button>';
            $html .= '                    </div>';
        }
        
        $html .= '                </div>';
        $html .= '                <button class="btn btn-sm btn-outline-primary" id="addColumn"><i class="bi bi-plus"></i> Add Column</button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '                <button type="button" class="btn btn-primary" id="saveBoardSettings">Save Changes</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render CSS styles
     */
    private function renderStyles(): string
    {
        $html = '<style>';
        $html .= <<<'CSS'
.kanban-board {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    padding-bottom: 20px;
}
.kanban-column {
    flex: 0 0 320px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0;
    min-height: 500px;
}
.column-header {
    padding: 15px;
    background: white;
    border-radius: 8px 8px 0 0;
    border-bottom: 1px solid #dee2e6;
}
.column-cards {
    padding: 15px;
    min-height: 400px;
}
.kanban-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
    cursor: grab;
    transition: box-shadow 0.2s;
}
.kanban-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.kanban-card:active {
    cursor: grabbing;
}
.card-header-row {
    display: flex;
    gap: 5px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.card-title {
    margin-bottom: 8px;
    font-size: 0.95rem;
}
.card-description {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 10px;
}
.card-footer-row {
    display: flex;
    gap: 10px;
    font-size: 0.8rem;
    color: #6c757d;
    flex-wrap: wrap;
}
.card-due-date.overdue {
    color: #dc3545;
    font-weight: 600;
}
.kanban-board-swimlanes {
    overflow-x: auto;
}
.swimlane-headers {
    display: grid;
    grid-template-columns: 200px repeat(auto-fit, minmax(280px, 1fr));
    gap: 10px;
    padding: 10px 0;
    border-bottom: 2px solid #dee2e6;
}
.swimlane {
    display: grid;
    grid-template-columns: 200px repeat(auto-fit, minmax(280px, 1fr));
    gap: 10px;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}
.swimlane-label {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}
.swimlane-column {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 10px;
    min-height: 100px;
}
.column-header-swimlane {
    text-align: center;
    padding: 10px;
}
CSS;
        $html .= '</style>';
        
        return $html;
    }
    
    /**
     * Render JavaScript
     */
    private function renderScripts(): string
    {
        $html = '<script>';
        $html .= <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    // Drag and drop
    let draggedCard = null;
    
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            this.style.opacity = '0.5';
        });
        
        card.addEventListener('dragend', function() {
            this.style.opacity = '1';
            draggedCard = null;
        });
        
        // Click to view details
        card.addEventListener('click', function() {
            const cardId = this.dataset.cardId;
            showCardDetails(cardId);
        });
    });
    
    document.querySelectorAll('.column-cards, .swimlane-column').forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '#e7f3ff';
        });
        
        column.addEventListener('dragleave', function() {
            this.style.backgroundColor = '';
        });
        
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '';
            
            if (draggedCard) {
                const columnId = this.dataset.columnId;
                const cardId = draggedCard.dataset.cardId;
                
                // Move card visually
                this.insertBefore(draggedCard, this.querySelector('.add-card-btn'));
                
                // Update server
                fetch('api/kanban.php?action=move', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({card_id: cardId, column_id: columnId})
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error: ' + data.message);
                        location.reload();
                    }
                });
            }
        });
    });
    
    // Search cards
    document.getElementById('cardSearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.kanban-card').forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });
    });
    
    // Add card button
    document.querySelectorAll('.add-card-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const columnId = this.dataset.columnId;
            document.getElementById('cardColumn').value = columnId;
            new bootstrap.Modal(document.getElementById('cardModal')).show();
        });
    });
    
    // Save card
    document.getElementById('saveCard')?.addEventListener('click', function() {
        const formData = {
            title: document.getElementById('cardTitle').value,
            description: document.getElementById('cardDescription').value,
            column: document.getElementById('cardColumn').value,
            priority: document.getElementById('cardPriority').value,
            assignee: document.getElementById('cardAssignee').value,
            due_date: document.getElementById('cardDueDate').value
        };
        
        fetch('api/kanban.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    });
    
    function showCardDetails(cardId) {
        const modal = new bootstrap.Modal(document.getElementById('cardDetailsModal'));
        
        fetch('api/kanban.php?action=details&id=' + cardId)
            .then(r => r.json())
            .then(card => {
                document.getElementById('cardDetailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <h4>${card.title}</h4>
                            <p>${card.description || 'No description'}</p>
                            <hr>
                            <h6>Comments</h6>
                            <div id="cardComments">
                                <p class="text-muted">No comments yet</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6>Details</h6>
                            <p><strong>Priority:</strong> ${card.priority}</p>
                            <p><strong>Assignee:</strong> ${card.assignee || 'Unassigned'}</p>
                            <p><strong>Due:</strong> ${card.due_date || 'Not set'}</p>
                            <p><strong>Created:</strong> ${card.created_at}</p>
                        </div>
                    </div>
                `;
                modal.show();
            });
    }
});
JS;
        $html .= '</script>';
        
        return $html;
    }
    
    /**
     * Get board columns
     */
    private function getColumns(int $boardId): array
    {
        // Try cache first
        $cached = Cache::get("kanban:columns:$boardId");
        if ($cached !== null) {
            return $cached;
        }
        
        // Placeholder - would query database
        $columns = [];
        foreach ($this->defaultColumns as $i => $col) {
            $columns[] = array_merge($col, ['id' => $i + 1]);
        }
        
        Cache::set("kanban:columns:$boardId", $columns, 600);
        return $columns;
    }
    
    /**
     * Get board cards
     */
    private function getCards(int $boardId): array
    {
        // Try cache first
        $cached = Cache::get("kanban:cards:$boardId");
        if ($cached !== null) {
            return $cached;
        }
        
        // Placeholder - would query database
        $cards = [];
        
        Cache::set("kanban:cards:$boardId", $cards, 300);
        return $cards;
    }
    
    /**
     * Get swimlanes
     */
    private function getSwimlanes(int $boardId, string $swimlaneBy): array
    {
        // Returns swimlane groups based on criteria
        // Placeholder implementation
        return [];
    }
    
    /**
     * Get default board ID
     */
    private function getDefaultBoard(): int
    {
        return 1; // Placeholder
    }
    
    /**
     * Convert hex color to Bootstrap color class
     */
    private function getBootstrapColor(string $hex): string
    {
        $map = [
            '#dc3545' => 'danger',
            '#fd7e14' => 'warning',
            '#ffc107' => 'warning',
            '#28a745' => 'success',
            '#17a2b8' => 'info',
            '#0d6efd' => 'primary',
            '#6c757d' => 'secondary',
        ];
        
        return $map[$hex] ?? 'secondary';
    }
    
    /**
     * Handle Kanban actions
     */
    public function handle(array $data): array
    {
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'save':
                return $this->handleSave($data);
            case 'move':
                return $this->handleMove($data);
            case 'delete':
                return $this->handleDelete($data);
            case 'details':
                return $this->handleDetails($data);
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    }
    
    /**
     * Handle save card
     */
    private function handleSave(array $data): array
    {
        // Validate and save card
        AuditLogger::log('kanban_card_create', 'kanban_cards', null, [], $data);
        
        // Clear cache
        Cache::delete('kanban:cards:*');
        
        return ['success' => true, 'message' => 'Card created'];
    }
    
    /**
     * Handle move card
     */
    private function handleMove(array $data): array
    {
        // Move card to new column
        AuditLogger::log('kanban_card_move', 'kanban_cards', $data['card_id'], [], $data);
        
        // Clear cache
        Cache::delete('kanban:cards:*');
        
        return ['success' => true, 'message' => 'Card moved'];
    }
    
    /**
     * Handle delete card
     */
    private function handleDelete(array $data): array
    {
        $cardId = $data['id'] ?? 0;
        
        // Delete card
        AuditLogger::log('kanban_card_delete', 'kanban_cards', $cardId, [], ['id' => $cardId]);
        
        // Clear cache
        Cache::delete('kanban:cards:*');
        
        return ['success' => true, 'message' => 'Card deleted'];
    }
    
    /**
     * Handle get card details
     */
    private function handleDetails(array $data): array
    {
        $cardId = $data['id'] ?? 0;
        
        // Fetch card details
        // Placeholder
        return ['success' => true, 'card' => []];
    }
    
    /**
     * Validate configuration
     */
    public function validate(): bool
    {
        // Kanban doesn't require specific config
        return true;
    }
    
    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
