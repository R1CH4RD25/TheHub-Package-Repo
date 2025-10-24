<?php
/**
 * Bullying Report Submission Form
 * Public-facing form - anyone can submit (even anonymous)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\Auth;

// Optional: Check if user is logged in (but don't require it)
$isLoggedIn = Auth::isLoggedIn();
$currentUser = $isLoggedIn ? Auth::getCurrentUser() : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Bullying - Woodson ISD</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .bullying-form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .form-header h1 {
            color: #d32f2f;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .confidential-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        
        .confidential-notice strong {
            color: #d32f2f;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .form-section h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group .optional {
            font-weight: normal;
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }
        
        .btn-submit {
            background: #d32f2f;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            background: #b71c1c;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .anonymous-warning {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .bullying-form-container {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="bullying-form-container">
        <div class="form-header">
            <h1>🛡️ Report Bullying or Harassment</h1>
            <p>Help us create a safe and respectful environment for everyone</p>
        </div>
        
        <div class="confidential-notice">
            <strong>⚠️ This report is confidential.</strong> Only school counselors, administrators, and the principal will have access to this information. Your report will be taken seriously and handled with care.
        </div>
        
        <form id="bullyingReportForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <?php if ($isLoggedIn): ?>
                <input type="hidden" name="reporter_user_id" value="<?php echo $currentUser['id']; ?>">
            <?php endif; ?>
            
            <!-- Reporter Information -->
            <div class="form-section">
                <h3>Your Information <span class="optional">(Optional - You can report anonymously)</span></h3>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="isAnonymous" name="is_anonymous" value="1">
                    <label for="isAnonymous"><strong>I want to remain anonymous</strong></label>
                </div>
                
                <div id="reporterFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reporterName">Your Name <span class="optional">(optional)</span></label>
                            <input type="text" id="reporterName" name="reporter_name" 
                                   value="<?php echo $isLoggedIn ? e($currentUser['first_name'] . ' ' . $currentUser['last_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="reporterRelationship">Your Relationship <span class="optional">(optional)</span></label>
                            <select id="reporterRelationship" name="reporter_relationship">
                                <option value="">Select...</option>
                                <option value="student">Student</option>
                                <option value="parent">Parent/Guardian</option>
                                <option value="teacher">Teacher</option>
                                <option value="staff">School Staff</option>
                                <option value="witness">Witness</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reporterEmail">Your Email <span class="optional">(optional)</span></label>
                            <input type="email" id="reporterEmail" name="reporter_email"
                                   value="<?php echo $isLoggedIn ? e($currentUser['email']) : ''; ?>">
                            <div class="help-text">We'll use this to follow up if needed</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reporterPhone">Your Phone <span class="optional">(optional)</span></label>
                            <input type="tel" id="reporterPhone" name="reporter_phone">
                        </div>
                    </div>
                    
                    <div class="anonymous-warning" id="anonymousWarning" style="display: none;">
                        ℹ️ <strong>Anonymous Reporting:</strong> Since you chose to remain anonymous, we won't be able to contact you for follow-up information. Please provide as much detail as possible in your report.
                    </div>
                </div>
            </div>
            
            <!-- Incident Details -->
            <div class="form-section">
                <h3>Incident Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="incidentDate">Date of Incident <span style="color: red;">*</span></label>
                        <input type="date" id="incidentDate" name="incident_date" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="incidentTime">Time of Incident <span class="optional">(approximate)</span></label>
                        <input type="time" id="incidentTime" name="incident_time">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="incidentLocation">Where did this happen? <span style="color: red;">*</span></label>
                    <input type="text" id="incidentLocation" name="incident_location" required
                           placeholder="e.g., Cafeteria, Hallway near gym, School bus, etc.">
                </div>
                
                <div class="form-group">
                    <label for="incidentType">Type of Bullying <span style="color: red;">*</span></label>
                    <select id="incidentType" name="incident_type" required>
                        <option value="">Select type...</option>
                        <option value="physical">Physical (hitting, pushing, etc.)</option>
                        <option value="verbal">Verbal (name-calling, threats, etc.)</option>
                        <option value="cyber">Cyberbullying (online, social media, texts)</option>
                        <option value="social">Social (exclusion, spreading rumors)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            
            <!-- Victim & Bully Information -->
            <div class="form-section">
                <h3>People Involved</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="victimName">Victim's Name <span class="optional">(if known)</span></label>
                        <input type="text" id="victimName" name="victim_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="victimGrade">Victim's Grade <span class="optional">(if known)</span></label>
                        <input type="text" id="victimGrade" name="victim_grade" placeholder="e.g., 9th, 10th">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bullyName">Alleged Bully's Name <span class="optional">(if known)</span></label>
                        <input type="text" id="bullyName" name="bully_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="bullyGrade">Alleged Bully's Grade <span class="optional">(if known)</span></label>
                        <input type="text" id="bullyGrade" name="bully_grade" placeholder="e.g., 11th, 12th">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="witnesses">Witnesses <span class="optional">(if any)</span></label>
                    <textarea id="witnesses" name="witnesses" 
                              placeholder="Names of people who saw what happened"></textarea>
                </div>
            </div>
            
            <!-- What Happened -->
            <div class="form-section">
                <h3>What Happened?</h3>
                
                <div class="form-group">
                    <label for="incidentDescription">Please describe what happened in detail <span style="color: red;">*</span></label>
                    <textarea id="incidentDescription" name="incident_description" required
                              placeholder="Be as specific as possible. Include what was said, what was done, and any other important details."
                              style="min-height: 180px;"></textarea>
                    <div class="help-text">Include dates, times, specific words or actions, and how this made you or the victim feel</div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="previousIncidents" name="previous_incidents" value="1">
                    <label for="previousIncidents">This has happened before</label>
                </div>
                
                <div class="form-group" id="previousIncidentsDetails" style="display: none;">
                    <label for="previousIncidentsText">Please describe previous incidents</label>
                    <textarea id="previousIncidentsText" name="previous_incidents_details"
                              placeholder="When did it happen before? What happened?"></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Submit Confidential Report</button>
        </form>
    </div>
    
    <script>
        // Toggle anonymous fields
        document.getElementById('isAnonymous').addEventListener('change', function() {
            const reporterFields = document.querySelectorAll('#reporterFields input, #reporterFields select');
            const warning = document.getElementById('anonymousWarning');
            
            if (this.checked) {
                reporterFields.forEach(field => {
                    if (field.id !== 'reporterRelationship') {
                        field.value = '';
                        field.disabled = true;
                    }
                });
                warning.style.display = 'block';
            } else {
                reporterFields.forEach(field => field.disabled = false);
                warning.style.display = 'none';
            }
        });
        
        // Toggle previous incidents details
        document.getElementById('previousIncidents').addEventListener('change', function() {
            document.getElementById('previousIncidentsDetails').style.display = 
                this.checked ? 'block' : 'none';
        });
        
        // Form submission
        document.getElementById('bullyingReportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            try {
                const formData = new FormData(this);
                const response = await fetch('/api/bullying-reports.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Thank you for your report. We take bullying seriously and will investigate this matter.\n\nReport ID: #' + result.report_id);
                    this.reset();
                    window.location.href = '/';
                } else {
                    alert('❌ Error: ' + (result.error || 'Failed to submit report'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = '🛡️ Submit Confidential Report';
                }
            } catch (error) {
                console.error('Submission error:', error);
                alert('❌ Network error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = '🛡️ Submit Confidential Report';
            }
        });
    </script>
</body>
</html>
