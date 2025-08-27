<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';
$cvData = null;
$viewMode = false;

// Process delete request
if (isset($_GET['delete_cv']) && is_numeric($_GET['delete_cv'])) {
    $cvId = $_GET['delete_cv'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cvs WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$cvId, $_SESSION['user_id']])) {
            $success = "CV deleted successfully!";
        } else {
            $error = "Failed to delete CV. Please try again.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
    
    // Redirect to avoid resubmission on refresh
    header("Location: generate_cv.php?success=" . urlencode($success));
    exit();
}

// Check if we're viewing a saved CV
if (isset($_GET['view_cv']) && is_numeric($_GET['view_cv'])) {
    $cvId = $_GET['view_cv'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cvs WHERE id = ? AND user_id = ?");
        $stmt->execute([$cvId, $_SESSION['user_id']]);
        $cvData = $stmt->fetch();
        
        if ($cvData) {
            $viewMode = true;
            // Decode JSON data
            $cvData['experiences'] = json_decode($cvData['experiences'], true);
            $cvData['education'] = json_decode($cvData['education'], true);
        } else {
            $error = "CV not found or you don't have permission to view it.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_cv'])) {
        // Get form data
        $fullName = $_POST['fullName'];
        $jobTitle = $_POST['jobTitle'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $location = $_POST['location'];
        $summary = $_POST['summary'];
        $skills = $_POST['skills'];
        
        // Process experiences
        $experiences = [];
        if (isset($_POST['exp_title'])) {
            $expCount = count($_POST['exp_title']);
            for ($i = 0; $i < $expCount; $i++) {
                if (!empty($_POST['exp_title'][$i])) {
                    $experiences[] = [
                        'title' => $_POST['exp_title'][$i],
                        'employer' => $_POST['exp_employer'][$i],
                        'duration' => $_POST['exp_duration'][$i],
                        'description' => $_POST['exp_description'][$i]
                    ];
                }
            }
        }
        
        // Process education
        $education = [];
        if (isset($_POST['edu_degree'])) {
            $eduCount = count($_POST['edu_degree']);
            for ($i = 0; $i < $eduCount; $i++) {
                if (!empty($_POST['edu_degree'][$i])) {
                    $education[] = [
                        'degree' => $_POST['edu_degree'][$i],
                        'institution' => $_POST['edu_institution'][$i],
                        'duration' => $_POST['edu_duration'][$i]
                    ];
                }
            }
        }
        
        try {
            // Check if we're updating an existing CV
            if (isset($_POST['cv_id']) && !empty($_POST['cv_id'])) {
                $cvId = $_POST['cv_id'];
                $stmt = $pdo->prepare("UPDATE cvs SET title = ?, full_name = ?, professional_title = ?, email = ?, phone = ?, location = ?, professional_summary = ?, skills = ?, experiences = ?, education = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                
                $cvTitle = $fullName . " - " . $jobTitle;
                $experiencesJson = json_encode($experiences);
                $educationJson = json_encode($education);
                
                if ($stmt->execute([
                    $cvTitle,
                    $fullName,
                    $jobTitle,
                    $email,
                    $phone,
                    $location,
                    $summary,
                    $skills,
                    $experiencesJson,
                    $educationJson,
                    $cvId,
                    $_SESSION['user_id']
                ])) {
                    $success = "CV updated successfully!";
                    // Reload the updated CV data
                    $stmt = $pdo->prepare("SELECT * FROM cvs WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cvId, $_SESSION['user_id']]);
                    $cvData = $stmt->fetch();
                    $cvData['experiences'] = json_decode($cvData['experiences'], true);
                    $cvData['education'] = json_decode($cvData['education'], true);
                    $viewMode = true;
                } else {
                    $error = "Failed to update CV. Please try again.";
                }
            } else {
                // Save new CV to database
                $stmt = $pdo->prepare("INSERT INTO cvs (user_id, title, full_name, professional_title, email, phone, location, professional_summary, skills, experiences, education) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $cvTitle = $fullName . " - " . $jobTitle;
                $experiencesJson = json_encode($experiences);
                $educationJson = json_encode($education);
                
                if ($stmt->execute([
                    $_SESSION['user_id'],
                    $cvTitle,
                    $fullName,
                    $jobTitle,
                    $email,
                    $phone,
                    $location,
                    $summary,
                    $skills,
                    $experiencesJson,
                    $educationJson
                ])) {
                    $success = "CV saved successfully!";
                    // Get the ID of the newly created CV
                    $cvId = $pdo->lastInsertId();
                    // Redirect to view mode
                    header("Location: generate_cv.php?view_cv=" . $cvId);
                    exit();
                } else {
                    $error = "Failed to save CV. Please try again.";
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get user's saved CVs
$savedCVs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM cvs WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $savedCVs = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading saved CVs: " . $e->getMessage();
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional CV Generator</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --success: #2ecc71;
            --warning: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-bottom: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .app-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 900px) {
            .app-container {
                flex-direction: column;
            }
        }
        
        .form-section {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }
        
        .preview-section {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            min-height: 600px;
        }
        
        h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark);
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        button:hover {
            background-color: var(--secondary);
        }
         .btn {
            padding: 10px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-generate {
            background-color: var(--success);
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 15px;
            font-size: 18px;
        }
        
        .btn-generate:hover {
            background-color: #27ae60;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 25px;
        }
        
        .add-btn {
            background-color: var(--accent);
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .cv-template {
            border: 1px solid #eee;
            padding: 25px;
            min-height: 500px;
        }
        
        .cv-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .cv-name {
            font-size: 28px;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .cv-title {
            font-size: 18px;
            color: var(--primary);
        }
        
        .cv-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        .cv-section {
            margin-bottom: 20px;
        }
        
        .cv-section-title {
            font-size: 20px;
            color: var(--secondary);
            padding-bottom: 5px;
            border-bottom: 2px solid var(--primary);
            margin-bottom: 10px;
        }
        
        .experience-item, .education-item {
            margin-bottom: 15px;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
        
        .item-subheader {
            display: flex;
            justify-content: space-between;
            color: var(--dark);
            font-style: italic;
            margin-bottom: 5px;
        }
        
        footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: var(--dark);
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-save {
            background-color: var(--success);
        }
        
        .btn-edit {
            background-color: var(--primary);
        }
        
        .btn-print {
            background-color: var(--dark);
        }
        
        .btn-delete {
            background-color: var(--accent);
        }
        
        .btn-download {
            background-color: var(--warning);
        }
        
        .hidden {
            display: none;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        
        .saved-cvs {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .cv-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .cv-item {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            transition: transform 0.2s;
            position: relative;
        }
        
        .cv-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .cv-item h3 {
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .cv-item p {
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .cv-date {
            font-size: 0.9em;
            color: #777;
        }
        
        .cv-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .cv-actions a, .cv-actions button {
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .view-link {
            background-color: var(--primary);
        }
        
        .edit-link {
            background-color: var(--success);
        }
        
        .print-link {
            background-color: var(--dark);
        }
        
        .delete-link {
            background-color: var(--accent);
        }
        
        /* Print styles for CV */
        @media print {
            body * {
                visibility: hidden;
            }
            .cv-template, .cv-template * {
                visibility: visible;
            }
            .cv-template {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 15px;
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Modal styles for delete confirmation */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-buttons button {
            padding: 8px 16px;
        }
        
        .btn-cancel {
            background-color: #95a5a6;
        }
        
        .btn-confirm-delete {
            background-color: var(--accent);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Professional CV Generator</h1>
            <p>Create a stunning CV in minutes and save it to your profile</p>
            <a href="logout.php" class="btn btn-danger">Logout</a>

        </div>
    </header>
    
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Display saved CVs -->
        <?php if (!empty($savedCVs)): ?>
        <div class="saved-cvs">
            <h2>Your Saved CVs</h2>
            <div class="cv-list">
                <?php foreach ($savedCVs as $cv): ?>
                <div class="cv-item">
                    <h3><?php echo htmlspecialchars($cv['title']); ?></h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($cv['full_name']); ?></p>
                    <p><strong>Title:</strong> <?php echo htmlspecialchars($cv['professional_title']); ?></p>
                    <p class="cv-date">Created: <?php echo date('M j, Y', strtotime($cv['created_at'])); ?></p>
                    <div class="cv-actions">
                        <a href="generate_cv.php?view_cv=<?php echo $cv['id']; ?>" class="view-link">View</a>
                        <a href="generate_cv.php?edit_cv=<?php echo $cv['id']; ?>" class="edit-link">Edit</a>
                        <a href="#" onclick="printCV()" class="print-link">Print</a>
                        <button onclick="confirmDelete(<?php echo $cv['id']; ?>)" class="delete-link">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="app-container">
            <?php if (!$viewMode): ?>
            <section class="form-section">
                <form method="POST" action="" id="cvForm">
                    <input type="hidden" name="cv_id" value="<?php echo isset($cvData['id']) ? $cvData['id'] : ''; ?>">
                    <h2>Personal Information</h2>
                    
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" name="fullName" placeholder="John Doe" required 
                               value="<?php echo isset($cvData['full_name']) ? htmlspecialchars($cvData['full_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jobTitle">Professional Title</label>
                        <input type="text" id="jobTitle" name="jobTitle" placeholder="Web Developer" required
                               value="<?php echo isset($cvData['professional_title']) ? htmlspecialchars($cvData['professional_title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="john.doe@example.com" required
                               value="<?php echo isset($cvData['email']) ? htmlspecialchars($cvData['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" placeholder="+1 234 567 890"
                               value="<?php echo isset($cvData['phone']) ? htmlspecialchars($cvData['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="New York, USA"
                               value="<?php echo isset($cvData['location']) ? htmlspecialchars($cvData['location']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="summary">Professional Summary</label>
                        <textarea id="summary" name="summary" placeholder="Experienced web developer with 5+ years in the industry..."><?php echo isset($cvData['professional_summary']) ? htmlspecialchars($cvData['professional_summary']) : ''; ?></textarea>
                    </div>
                    
                    <div class="section-title">
                        <h2>Work Experience</h2>
                        <button type="button" class="add-btn" id="addExperience">+ Add</button>
                    </div>
                    
                    <div id="experienceForms">
                        <?php if (isset($cvData['experiences']) && !empty($cvData['experiences'])): ?>
                            <?php foreach ($cvData['experiences'] as $exp): ?>
                            <div class="form-group experience-form">
                                <label>Job Title</label>
                                <input type="text" name="exp_title[]" class="exp-title" placeholder="Senior Developer" value="<?php echo htmlspecialchars($exp['title']); ?>">
                                
                                <label>Employer</label>
                                <input type="text" name="exp_employer[]" class="exp-employer" placeholder="Tech Solutions Inc." value="<?php echo htmlspecialchars($exp['employer']); ?>">
                                
                                <label>Duration</label>
                                <input type="text" name="exp_duration[]" class="exp-duration" placeholder="Jan 2020 - Present" value="<?php echo htmlspecialchars($exp['duration']); ?>">
                                
                                <label>Description</label>
                                <textarea name="exp_description[]" class="exp-description" placeholder="Developed web applications using HTML, CSS, JavaScript..."><?php echo htmlspecialchars($exp['description']); ?></textarea>
                                
                                <button type="button" class="remove-btn" style="background-color: var(--accent); margin-top: 5px;">Remove</button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="form-group experience-form">
                                <label>Job Title</label>
                                <input type="text" name="exp_title[]" class="exp-title" placeholder="Senior Developer">
                                
                                <label>Employer</label>
                                <input type="text" name="exp_employer[]" class="exp-employer" placeholder="Tech Solutions Inc.">
                                
                                <label>Duration</label>
                                <input type="text" name="exp_duration[]" class="exp-duration" placeholder="Jan 2020 - Present">
                                
                                <label>Description</label>
                                <textarea name="exp_description[]" class="exp-description" placeholder="Developed web applications using HTML, CSS, JavaScript..."></textarea>
                                
                                <button type="button" class="remove-btn" style="background-color: var(--accent); margin-top: 5px;">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-title">
                        <h2>Education</h2>
                        <button type="button" class="add-btn" id="addEducation">+ Add</button>
                    </div>
                    
                    <div id="educationForms">
                        <?php if (isset($cvData['education']) && !empty($cvData['education'])): ?>
                            <?php foreach ($cvData['education'] as $edu): ?>
                            <div class="form-group education-form">
                                <label>Degree</label>
                                <input type="text" name="edu_degree[]" class="edu-degree" placeholder="Bachelor of Computer Science" value="<?php echo htmlspecialchars($edu['degree']); ?>">
                                
                                <label>Institution</label>
                                <input type="text" name="edu_institution[]" class="edu-institution" placeholder="University of Technology" value="<?php echo htmlspecialchars($edu['institution']); ?>">
                                
                                <label>Duration</label>
                                <input type="text" name="edu_duration[]" class="edu-duration" placeholder="2014 - 2018" value="<?php echo htmlspecialchars($edu['duration']); ?>">
                                
                                <button type="button" class="remove-btn" style="background-color: var(--accent); margin-top: 5px;">Remove</button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="form-group education-form">
                                <label>Degree</label>
                                <input type="text" name="edu_degree[]" class="edu-degree" placeholder="Bachelor of Computer Science">
                                
                                <label>Institution</label>
                                <input type="text" name="edu_institution[]" class="edu-institution" placeholder="University of Technology">
                                
                                <label>Duration</label>
                                <input type="text" name="edu_duration[]" class="edu-duration" placeholder="2014 - 2018">
                                
                                <button type="button" class="remove-btn" style="background-color: var(--accent); margin-top: 5px;">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="skills">Skills (comma separated)</label>
                        <textarea id="skills" name="skills" placeholder="HTML, CSS, JavaScript, MySQL, PHP"><?php echo isset($cvData['skills']) ? htmlspecialchars($cvData['skills']) : ''; ?></textarea>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="save_cv" class="btn-save">Save CV</button>
                        <button type="button" class="btn-download" id="downloadCV">Download PDF</button>
                        <button type="button" class="btn-print no-print" onclick="printCV()">Print CV</button>
                    </div>
                </form>
            </section>
            <?php endif; ?>
            
            <section class="preview-section">
                <h2>CV Preview <?php if ($viewMode) echo ' - View Mode'; ?></h2>
                
                <?php if ($viewMode): ?>
                <div class="action-buttons">
                    <a href="generate_cv.php" class="btn-edit" style="text-decoration: none;">Create New CV</a>
                    <a href="generate_cv.php?edit_cv=<?php echo $cvData['id']; ?>" class="btn-save" style="text-decoration: none;">Edit This CV</a>
                    <button class="btn-print" onclick="printCV()">Print CV</button>
                    <button class="btn-delete" onclick="confirmDelete(<?php echo $cvData['id']; ?>)">Delete CV</button>
                </div>
                <?php endif; ?>
                
                <div class="cv-template" id="cvPreview">
                    <div class="cv-header">
                        <div class="cv-name" id="previewName">
                            <?php echo $viewMode ? htmlspecialchars($cvData['full_name']) : 'John Doe'; ?>
                        </div>
                        <div class="cv-title" id="previewTitle">
                            <?php echo $viewMode ? htmlspecialchars($cvData['professional_title']) : 'Web Developer'; ?>
                        </div>
                        <div class="cv-contact">
                            <span id="previewEmail">
                                <?php echo $viewMode ? htmlspecialchars($cvData['email']) : 'john.doe@example.com'; ?>
                            </span>
                            <span id="previewPhone">
                                <?php echo $viewMode ? htmlspecialchars($cvData['phone']) : '+1 234 567 890'; ?>
                            </span>
                            <span id="previewLocation">
                                <?php echo $viewMode ? htmlspecialchars($cvData['location']) : 'New York, USA'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="cv-section">
                        <div class="cv-section-title">Professional Summary</div>
                        <p id="previewSummary">
                            <?php echo $viewMode ? nl2br(htmlspecialchars($cvData['professional_summary'])) : 'Experienced web developer with 5+ years in the industry...'; ?>
                        </p>
                    </div>
                    
                    <div class="cv-section">
                        <div class="cv-section-title">Work Experience</div>
                        <div id="previewExperience">
                            <?php if ($viewMode && !empty($cvData['experiences'])): ?>
                                <?php foreach ($cvData['experiences'] as $exp): ?>
                                <div class="experience-item">
                                    <div class="item-header"><?php echo htmlspecialchars($exp['title']); ?></div>
                                    <div class="item-subheader">
                                        <span><?php echo htmlspecialchars($exp['employer']); ?></span>
                                        <span><?php echo htmlspecialchars($exp['duration']); ?></span>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="experience-item">
                                    <div class="item-header">Senior Developer</div>
                                    <div class="item-subheader">
                                        <span>Tech Solutions Inc.</span>
                                        <span>Jan 2020 - Present</span>
                                    </div>
                                    <p>Developed web applications using HTML, CSS, JavaScript...</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="cv-section">
                        <div class="cv-section-title">Education</div>
                        <div id="previewEducation">
                            <?php if ($viewMode && !empty($cvData['education'])): ?>
                                <?php foreach ($cvData['education'] as $edu): ?>
                                <div class="education-item">
                                    <div class="item-header"><?php echo htmlspecialchars($edu['degree']); ?></div>
                                    <div class="item-subheader">
                                        <span><?php echo htmlspecialchars($edu['institution']); ?></span>
                                        <span><?php echo htmlspecialchars($edu['duration']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="education-item">
                                    <div class="item-header">Bachelor of Computer Science</div>
                                    <div class="item-subheader">
                                        <span>University of Technology</span>
                                        <span>2014 - 2018</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="cv-section">
                        <div class="cv-section-title">Skills</div>
                        <p id="previewSkills">
                            <?php echo $viewMode ? nl2br(htmlspecialchars($cvData['skills'])) : 'HTML, CSS, JavaScript, MySQL, PHP'; ?>
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this CV? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button onclick="closeModal()" class="btn-cancel">Cancel</button>
                <button id="confirmDeleteBtn" class="btn-confirm-delete">Delete</button>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p>CV Generator App &copy; 2025 | HTML, CSS, JavaScript Frontend with MySQL Database Backend | Developed by Maclean Nimoh Antwi</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add experience form
            document.getElementById('addExperience')?.addEventListener('click', function() {
                const newForm = document.querySelector('.experience-form').cloneNode(true);
                // Clear input values
                newForm.querySelectorAll('input, textarea').forEach(input => input.value = '');
                document.getElementById('experienceForms').appendChild(newForm);
                addRemoveListeners();
            });
            
            // Add education form
            document.getElementById('addEducation')?.addEventListener('click', function() {
                const newForm = document.querySelector('.education-form').cloneNode(true);
                // Clear input values
                newForm.querySelectorAll('input').forEach(input => input.value = '');
                document.getElementById('educationForms').appendChild(newForm);
                addRemoveListeners();
            });
            
            // Add remove button functionality
            function addRemoveListeners() {
                document.querySelectorAll('.remove-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        if (document.querySelectorAll('.experience-form').length > 1 || 
                            document.querySelectorAll('.education-form').length > 1) {
                            this.parentElement.remove();
                            updateCVPreview();
                        }
                    });
                });
            }
            
            // Add input event listeners for real-time preview
            const formInputs = document.querySelectorAll('#cvForm input, #cvForm textarea');
            formInputs.forEach(input => {
                input.addEventListener('input', updateCVPreview);
            });
            
            // Initial call to add remove listeners
            addRemoveListeners();
            
            // Function to update the CV preview
            function updateCVPreview() {
                // Update personal info
                document.getElementById('previewName').textContent = document.getElementById('fullName')?.value || 'John Doe';
                document.getElementById('previewTitle').textContent = document.getElementById('jobTitle')?.value || 'Web Developer';
                document.getElementById('previewEmail').textContent = document.getElementById('email')?.value || 'john.doe@example.com';
                document.getElementById('previewPhone').textContent = document.getElementById('phone')?.value || '+1 234 567 890';
                document.getElementById('previewLocation').textContent = document.getElementById('location')?.value || 'New York, USA';
                document.getElementById('previewSummary').textContent = document.getElementById('summary')?.value || 'Experienced web developer with 5+ years in the industry...';
                
                // Update experience
                const experienceForms = document.querySelectorAll('.experience-form');
                let experienceHTML = '';
                
                experienceForms.forEach(form => {
                    const title = form.querySelector('.exp-title').value || 'Job Title';
                    const employer = form.querySelector('.exp-employer').value || 'Employer';
                    const duration = form.querySelector('.exp-duration').value || 'Duration';
                    const description = form.querySelector('.exp-description').value || 'Description of responsibilities and achievements...';
                    
                    experienceHTML += `
                        <div class="experience-item">
                            <div class="item-header">${title}</div>
                            <div class="item-subheader">
                                <span>${employer}</span>
                                <span>${duration}</span>
                            </div>
                            <p>${description}</p>
                        </div>
                    `;
                });
                
                document.getElementById('previewExperience').innerHTML = experienceHTML || 
                    '<div class="experience-item"><p>No experience added yet.</p></div>';
                
                // Update education
                const educationForms = document.querySelectorAll('.education-form');
                let educationHTML = '';
                
                educationForms.forEach(form => {
                    const degree = form.querySelector('.edu-degree').value || 'Degree';
                    const institution = form.querySelector('.edu-institution').value || 'Institution';
                    const duration = form.querySelector('.edu-duration').value || 'Duration';
                    
                    educationHTML += `
                        <div class="education-item">
                            <div class="item-header">${degree}</div>
                            <div class="item-subheader">
                                <span>${institution}</span>
                                <span>${duration}</span>
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('previewEducation').innerHTML = educationHTML || 
                    '<div class="education-item"><p>No education added yet.</p></div>';
                
                // Update skills
                document.getElementById('previewSkills').textContent = document.getElementById('skills')?.value || 'HTML, CSS, JavaScript, MySQL, PHP';
            }
            
            // Initialize with a basic preview if not in view mode
            if (!<?php echo $viewMode ? 'true' : 'false'; ?>) {
                updateCVPreview();
            }
        });
        
        // Print CV function
        function printCV() {
            window.print();
        }
        
        // Delete confirmation modal functions
        function confirmDelete(cvId) {
            const modal = document.getElementById('deleteModal');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            confirmBtn.onclick = function() {
                window.location.href = 'generate_cv.php?delete_cv=' + cvId;
            };
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>