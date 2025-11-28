<?php
/**
 * Patient Files Viewer
 * Shows all patient files organized by patient
 */
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../components/helpers.php';

// Get all patients with their files
$patients = [];
$sql = "SELECT id, name, consultant_doctor FROM patients ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $patient_id = $row['id'];
        $upload_dir = __DIR__ . "/../medical/uploads/medical/" . $patient_id;
        $upload_url_base = "../medical/uploads/medical/" . $patient_id;
        
        $files = [];
        if (is_dir($upload_dir)) {
            $dir_files = scandir($upload_dir);
            foreach ($dir_files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($upload_dir . '/' . $file)) {
                    $file_path = $upload_dir . '/' . $file;
                    $file_url = $upload_url_base . '/' . rawurlencode($file);
                    $file_size = filesize($file_path);
                    $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    
                    // Determine file type icon
                    $icon = 'fa-file';
                    if (in_array($file_ext, ['pdf'])) {
                        $icon = 'fa-file-pdf';
                    } elseif (in_array($file_ext, ['doc', 'docx'])) {
                        $icon = 'fa-file-word';
                    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $icon = 'fa-file-image';
                    }
                    
                    $files[] = [
                        'name' => $file,
                        'url' => $file_url,
                        'size' => $file_size,
                        'size_formatted' => formatBytes($file_size),
                        'icon' => $icon,
                        'ext' => $file_ext
                    ];
                }
            }
        }
        
        if (!empty($files)) {
            $row['files'] = $files;
            $patients[] = $row;
        }
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Files</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
    <style>
        .patient-files-container {
            padding: 20px;
            background: transparent;
            min-height: 100vh;
        }
        .patient-card {
            background: transparent;
            border-radius: 16px;
            border: 1px solid rgba(10, 42, 22, 0.12);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .patient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .patient-header h5 {
            margin: 0;
            font-weight: 600;
        }
        .patient-info {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(10, 42, 22, 0.12);
            background: transparent;
        }
        .patient-info small {
            color: #6c757d;
        }
        .files-list {
            padding: 15px 20px;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid rgba(10, 42, 22, 0.12);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.2s;
            background: transparent;
        }
        .file-item:hover {
            border-color: #29603e;
            transform: translateX(4px);
        }
        .file-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(41, 96, 62, 0.12);
            border-radius: 8px;
            margin-right: 15px;
            color: #29603e;
            font-size: 1.2rem;
        }
        .file-info {
            flex: 1;
        }
        .file-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }
        .file-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .btn-file {
            padding: 6px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            border: 1px dashed rgba(10, 42, 22, 0.2);
            border-radius: 16px;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body class="patient-surface">
    <div class="patient-shell">
        <div class="glass-panel patient-files-card">
            <div class="patient-files-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-folder-open"></i> Patient Files</h2>
            <div class="text-muted">
                <i class="fas fa-info-circle"></i> <?php echo count($patients); ?> patient(s) with files
            </div>
        </div>

        <?php if (empty($patients)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h4>No Patient Files Found</h4>
                <p>Patient files will appear here once they are uploaded through the medical information section.</p>
            </div>
        <?php else: ?>
            <?php foreach ($patients as $patient): ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <h5>
                            <i class="fas fa-user-injured"></i>
                            <?php echo htmlspecialchars($patient['name']); ?>
                        </h5>
                        <span class="badge bg-light text-dark">
                            PAT<?php echo $patient['id']; ?>
                        </span>
                    </div>
                    <div class="patient-info">
                        <small>
                            <i class="fas fa-user-md"></i> Consultant: 
                            <?php echo htmlspecialchars($patient['consultant_doctor'] ?: 'N/A'); ?>
                        </small>
                    </div>
                    <div class="files-list">
                        <h6 class="mb-3">
                            <i class="fas fa-file"></i> Files (<?php echo count($patient['files']); ?>)
                        </h6>
                        <?php foreach ($patient['files'] as $file): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <i class="fas <?php echo $file['icon']; ?>"></i>
                                </div>
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                                    <div class="file-meta">
                                        <i class="fas fa-file"></i> <?php echo strtoupper($file['ext']); ?> 
                                        &nbsp;|&nbsp;
                                        <i class="fas fa-weight"></i> <?php echo $file['size_formatted']; ?>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="<?php echo htmlspecialchars($file['url']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-file">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="<?php echo htmlspecialchars($file['url']); ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-primary btn-file">
                                        <i class="fas fa-external-link-alt"></i> Open
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>


