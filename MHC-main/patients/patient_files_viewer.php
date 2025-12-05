<?php

/**
 * Patient Files Viewer (DB based, with filters)
 * Only shows results when search or filters are applied
 */
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';

/* ---------- small HTML escape helper ---------- */
function h($v)
{
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------- read filters from GET ---------- */
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$fileType   = isset($_GET['file_type']) ? trim($_GET['file_type']) : '';
$dateFrom   = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo     = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Check if any filter is applied
$hasFilters = !empty($search) || !empty($fileType) || !empty($dateFrom) || !empty($dateTo);

/* ---------- check patient_files table exists ---------- */
$hasPatientFiles = false;
if ($result = $conn->query("SHOW TABLES LIKE 'patient_files'")) {
    $hasPatientFiles = $result->num_rows > 0;
    $result->close();
}

$patients = [];

// Only fetch data if filters are applied
if ($hasPatientFiles && $hasFilters) {

    // --- build dynamic WHERE + params ---
    $where  = "1=1";
    $params = [];
    $types  = "";

    if ($search !== '') {
        // search in patient name, PAT{id} and file name
        $where .= " AND (p.name LIKE ? OR CONCAT('PAT', p.id) LIKE ? OR pf.file_name LIKE ?)";
        $like = "%" . $search . "%";
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($fileType !== '' && in_array($fileType, ['pre_case_taking', 'case_taking', 'report'], true)) {
        $where   .= " AND pf.file_type = ?";
        $types   .= "s";
        $params[] = $fileType;
    }

    if ($dateFrom !== '') {
        $where   .= " AND DATE(pf.created_at) >= ?";
        $types   .= "s";
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where   .= " AND DATE(pf.created_at) <= ?";
        $types   .= "s";
        $params[] = $dateTo;
    }

    $sql = "
        SELECT
            pf.id,
            pf.patient_id,
            pf.case_id,
            pf.file_type,
            pf.file_name,
            pf.file_path,
            pf.file_size,
            pf.created_at,
            p.name AS patient_name
        FROM patient_files pf
        INNER JOIN patients p ON pf.patient_id = p.id
        WHERE $where
        ORDER BY p.name, pf.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            // bind dynamic params
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $pid = (int)$row['patient_id'];
            if (!isset($patients[$pid])) {
                $patients[$pid] = [
                    'id'    => $pid,
                    'name'  => $row['patient_name'],
                    'files' => []
                ];
            }

            $fileName = $row['file_name'];
            $filePath = $row['file_path'];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $icon     = 'fa-file';
            if ($ext === 'pdf') {
                $icon = 'fa-file-pdf';
            } elseif (in_array($ext, ['doc', 'docx'])) {
                $icon = 'fa-file-word';
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $icon = 'fa-file-image';
            }

            // Build absolute file path to check existence
            $absolutePath = __DIR__ . "/../" . $filePath;
            $fileExists = file_exists($absolutePath);

            // Use file_path from database
            $url = "../" . $filePath;

            $patients[$pid]['files'][] = [
                'name'           => $fileName,
                'url'            => $url,
                'exists'         => $fileExists,
                'size_formatted' => $row['file_size'],
                'icon'           => $icon,
                'ext'            => $ext,
                'type'           => $row['file_type'],
                'created_at'     => $row['created_at'],
                'case_id'        => $row['case_id']
            ];
        }
        $res->close();
        $stmt->close();
    } else {
        echo "<p class='alert alert-danger'>Query Error: " . h($conn->error) . "</p>";
    }
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

    <style>
        body {
            background: #f5f5f5;
            padding: 20px 0;
        }

        .patient-files-container {
            padding: 20px;
        }

        .patient-files-card {
            background: linear-gradient(135deg, #d9e7df 0%, #c5d7cd 100%);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.10);
        }

        .filters-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            border: 1px solid rgba(148, 163, 184, 0.5);
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .patient-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(10, 42, 22, 0.12);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .patient-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .patient-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-info {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(10, 42, 22, 0.12);
        }

        .files-list {
            padding: 16px 20px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 14px;
            border: 1px solid rgba(10, 42, 22, 0.12);
            border-radius: 10px;
            margin-bottom: 12px;
            transition: all 0.2s;
            background: #f9fafb;
        }

        .file-item:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateX(4px);
        }

        .file-item.missing {
            opacity: 0.7;
            background: #fef2f2;
        }

        .file-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(16, 185, 129, 0.12);
            border-radius: 8px;
            margin-right: 15px;
            color: #10b981;
            font-size: 1.3rem;
        }

        .file-item.missing .file-icon {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .file-meta {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .file-actions {
            display: flex;
            gap: 10px;
        }

        .btn-file {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-download {
            background: #10b981;
            color: white;
        }

        .btn-download:hover {
            background: #059669;
            color: white;
            text-decoration: none;
        }

        .btn-view {
            background: #f3f4f6;
            color: #10b981;
            border: 1px solid #e5e7eb;
        }

        .btn-view:hover {
            background: #e5f6f2;
            border-color: #10b981;
            text-decoration: none;
        }

        .btn-missing {
            background: #fecaca;
            color: #991b1b;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-missing:hover {
            background: #fecaca;
            color: #991b1b;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            border: 2px dashed rgba(10, 42, 22, 0.2);
            border-radius: 16px;
            background: #fafbfc;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.4;
            color: #10b981;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0b3c1f;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .initial-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }

        .initial-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #10b981;
        }

        .initial-state h4 {
            color: #1f2937;
            font-weight: 700;
        }

        .initial-state p {
            font-size: 0.95rem;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="patient-files-container">
        <div class="patient-files-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="page-title"><i class="fas fa-folder-open me-2"></i>Patient Files</h2>
                    <p class="page-subtitle">
                        <i class="fas fa-info-circle me-1"></i>
                        <?php
                        if (!$hasPatientFiles) {
                            echo "patient_files table not found";
                        } elseif ($hasFilters) {
                            echo count($patients) . " result(s) found";
                        } else {
                            echo "Enter search criteria to view files";
                        }
                        ?>
                    </p>
                </div>
                <a href="../patients/patients_view.php" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Patients
                </a>
            </div>

            <!-- FILTERS -->
            <form method="get" class="filters-card">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1"><i class="fas fa-search me-1"></i>Search</label>
                        <input type="text"
                            name="search"
                            class="form-control form-control-sm"
                            placeholder="Patient name, PAT ID, or file name..."
                            value="<?= h($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1"><i class="fas fa-filter me-1"></i>File Type</label>
                        <select name="file_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="pre_case_taking" <?= $fileType === 'pre_case_taking' ? 'selected' : ''; ?>>Pre Case Taking</option>
                            <option value="case_taking" <?= $fileType === 'case_taking' ? 'selected' : ''; ?>>Case Taking</option>
                            <option value="report" <?= $fileType === 'report' ? 'selected' : ''; ?>>Report</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1"><i class="fas fa-calendar me-1"></i>From</label>
                        <input type="date"
                            name="date_from"
                            class="form-control form-control-sm"
                            value="<?= h($dateFrom); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1"><i class="fas fa-calendar me-1"></i>To</label>
                        <input type="date"
                            name="date_to"
                            class="form-control form-control-sm"
                            value="<?= h($dateTo); ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-sm flex-fill">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="patient_files_viewer.php" class="btn btn-outline-secondary btn-sm flex-fill">
                            Clear
                        </a>
                    </div>
                </div>
            </form>

            <!-- RESULTS -->
            <?php if (!$hasPatientFiles): ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <h4 class="mt-2">patient_files Table Not Found</h4>
                    <p class="mt-2">Create the table in your database to start tracking files.</p>
                </div>
            <?php elseif (!$hasFilters): ?>
                <div class="initial-state">
                    <i class="fas fa-search"></i>
                    <h4>Search for Patient Files</h4>
                    <p>Use the filters above to search for patient files by name, file type, or date range.</p>
                    <p class="text-muted small" style="margin-top: 15px;">
                        <i class="fas fa-lightbulb me-1"></i>
                        Try searching by patient name or file type to get started.
                    </p>
                </div>
            <?php elseif (empty($patients)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4 class="mt-2">No Files Found</h4>
                    <p class="mt-2">No patient files match your search criteria.</p>
                    <a href="patient_files_viewer.php" class="btn btn-success btn-sm mt-3">
                        <i class="fas fa-redo me-1"></i>Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($patients as $p): ?>
                    <div class="patient-card">
                        <div class="patient-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-injured me-2"></i><?= h($p['name']); ?>
                            </h5>
                            <span class="badge bg-light text-dark">PAT#<?= $p['id']; ?></span>
                        </div>
                        <div class="patient-info">
                            <small><i class="fas fa-file me-1"></i><?= count($p['files']); ?> file(s)</small>
                        </div>
                        <div class="files-list">
                            <?php foreach ($p['files'] as $file): ?>
                                <div class="file-item <?= !$file['exists'] ? 'missing' : ''; ?>">
                                    <div class="file-icon">
                                        <i class="fas <?= $file['icon']; ?>"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name">
                                            <?= h($file['name']); ?>
                                            <?php if (!$file['exists']): ?>
                                                <span class="badge bg-danger ms-2" title="File not found on server">Missing</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="file-meta">
                                            <span><i class="fas fa-file"></i> <?= strtoupper($file['ext']); ?></span>
                                            <span class="ms-2"><i class="fas fa-tag"></i> <?= h($file['type']); ?></span>
                                            <span class="ms-2"><i class="fas fa-weight"></i> <?= h($file['size_formatted']); ?></span>
                                            <span class="ms-2"><i class="fas fa-clock"></i> <?= h($file['created_at']); ?></span>
                                            <?php if ($file['case_id']): ?>
                                                <span class="ms-2 badge bg-info">Case #<?= $file['case_id']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <?php if ($file['exists']): ?>
                                            <a href="<?= h($file['url']); ?>" download class="btn-file btn-download">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                            <a href="<?= h($file['url']); ?>" target="_blank" class="btn-file btn-view">
                                                <i class="fas fa-external-link-alt me-1"></i>Open
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-file btn-missing" disabled title="File not found on server">
                                                <i class="fas fa-ban me-1"></i>File Missing
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>