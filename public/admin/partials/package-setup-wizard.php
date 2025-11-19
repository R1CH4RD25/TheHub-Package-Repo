<!-- Package Setup Wizard Modal -->
<div class="modal fade" id="packageSetupWizardModal" tabindex="-1" aria-labelledby="packageSetupWizardTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="packageSetupWizardTitle">
                    <i class="fas fa-magic"></i> Package Setup Wizard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="min-height: 400px;">
                <!-- Progress Bar -->
                <div class="wizard-progress" style="margin-bottom: 2rem;">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="wizard-steps" style="display: flex; justify-content: space-between; margin-top: 1rem;">
                        <div class="wizard-step-indicator active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Category</div>
                        </div>
                        <div class="wizard-step-indicator" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Capabilities</div>
                        </div>
                        <div class="wizard-step-indicator" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Notifications</div>
                        </div>
                        <div class="wizard-step-indicator" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-label">Guidelines</div>
                        </div>
                        <div class="wizard-step-indicator" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>
                </div>

                <!-- Wizard Content -->
                <div id="wizardContent">
                    <!-- Step 1: Category -->
                    <div id="wizardStep1" class="wizard-step-content active" data-step="1">
                        <h4>📂 Choose Package Category</h4>
                        <p class="text-muted">Categories help organize packages and determine default capabilities.</p>

                        <div class="category-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                            <div class="category-card" data-category="reporting" onclick="selectWizardCategory('reporting')">
                                <i class="fas fa-chart-bar fa-3x" style="color: #3b82f6;"></i>
                                <h5>Reporting</h5>
                                <p>Incident reports, forms, submissions</p>
                            </div>
                            <div class="category-card" data-category="communication" onclick="selectWizardCategory('communication')">
                                <i class="fas fa-comments fa-3x" style="color: #8b5cf6;"></i>
                                <h5>Communication</h5>
                                <p>Announcements, messages, posts</p>
                            </div>
                            <div class="category-card" data-category="administrative" onclick="selectWizardCategory('administrative')">
                                <i class="fas fa-cog fa-3x" style="color: #ef4444;"></i>
                                <h5>Administrative</h5>
                                <p>Management tools, settings</p>
                            </div>
                            <div class="category-card" data-category="resource" onclick="selectWizardCategory('resource')">
                                <i class="fas fa-folder fa-3x" style="color: #10b981;"></i>
                                <h5>Resource</h5>
                                <p>Files, documents, assets</p>
                            </div>
                            <div class="category-card" data-category="safety" onclick="selectWizardCategory('safety')">
                                <i class="fas fa-shield-alt fa-3x" style="color: #f59e0b;"></i>
                                <h5>Safety</h5>
                                <p>Compliance, investigations</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Capabilities -->
                    <div id="wizardStep2" class="wizard-step-content" data-step="2" style="display: none;">
                        <h4>🎯 Configure Capabilities</h4>
                        <p class="text-muted">Select which roles can perform actions in this package.</p>

                        <div id="wizardCapabilitiesGrid" style="margin-top: 1.5rem;">
                            <!-- Will be populated dynamically based on category -->
                        </div>
                    </div>

                    <!-- Step 3: Notifications -->
                    <div id="wizardStep3" class="wizard-step-content" data-step="3" style="display: none;">
                        <h4>📧 Notification Rules</h4>
                        <p class="text-muted">Configure who receives email notifications for package events.</p>

                        <div style="margin-top: 1.5rem;">
                            <div class="form-group mb-3">
                                <label><strong>Notify on new submissions:</strong></label>
                                <input type="email" class="form-control" id="wizardNotifyEmail" placeholder="admin@example.com, manager@example.com">
                                <small class="text-muted">Comma-separated email addresses</small>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="wizardNotifySubmitter" checked>
                                <label class="form-check-label" for="wizardNotifySubmitter">
                                    Notify submitter on status changes
                                </label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wizardNotifyApprovers">
                                <label class="form-check-label" for="wizardNotifyApprovers">
                                    Notify approvers when action required
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Guidelines -->
                    <div id="wizardStep4" class="wizard-step-content" data-step="4" style="display: none;">
                        <h4>📝 User Guidelines</h4>
                        <p class="text-muted">Provide instructions that users will see when using this package.</p>

                        <div style="margin-top: 1.5rem;">
                            <textarea class="form-control" id="wizardGuidelines" rows="8"
                                placeholder="Enter user-facing instructions...

Example:
• Submit reports within 24 hours of incidents
• Include detailed descriptions and relevant documentation
• Use appropriate priority levels
• Contact admin@example.com for urgent issues"></textarea>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div id="wizardStep5" class="wizard-step-content" data-step="5" style="display: none;">
                        <h4>✅ Review Configuration</h4>
                        <p class="text-muted">Review your settings before finalizing.</p>

                        <div id="wizardReviewSummary" style="margin-top: 1.5rem;">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="wizardSkipBtn" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Skip Wizard
                </button>
                <button type="button" class="btn btn-secondary" id="wizardPrevBtn" onclick="previousWizardStep()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="btn btn-primary" id="wizardNextBtn" onclick="nextWizardStep()">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="button" class="btn btn-success" id="wizardFinishBtn" onclick="finishWizardSetup()" style="display: none;">
                    <i class="fas fa-check"></i> Finish Setup
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    padding: 2rem 1rem;
    text-align: center;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.category-card:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.2);
}

.category-card.selected {
    border-color: #3b82f6;
    background: #dbeafe;
}

.category-card h5 {
    margin: 1rem 0 0.5rem 0;
    font-weight: 600;
}

.category-card p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

.wizard-steps {
    display: flex;
    justify-content: space-between;
}

.wizard-step-indicator {
    text-align: center;
    flex: 1;
    position: relative;
}

.wizard-step-indicator .step-number {
    width: 40px;
    height: 40px;
    margin: 0 auto 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e5e7eb;
    color: #6b7280;
    font-weight: 600;
    transition: all 0.3s;
}

.wizard-step-indicator.active .step-number {
    background: #3b82f6;
    color: white;
}

.wizard-step-indicator.completed .step-number {
    background: #10b981;
    color: white;
}

.wizard-step-indicator .step-label {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 500;
}

.wizard-step-indicator.active .step-label {
    color: #3b82f6;
    font-weight: 600;
}

.wizard-step-content {
    display: none;
}

.wizard-step-content.active {
    display: block;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.wizard-review-section {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.wizard-review-section h6 {
    margin: 0 0 0.75rem 0;
    font-weight: 600;
    color: #333;
}
</style>
