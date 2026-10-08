<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$pdo = getPdo();

try {
    $user_id = $_SESSION['user_id'];
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required']);
        exit;
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid email is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already taken']);
        exit;
    }

    $profile_picture = null;
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024;

        $file = $_FILES['profile_picture'];
        
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Maximum size is 5MB.']);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.']);
            exit;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file extension.']);
            exit;
        }
        
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
        
        $upload_dir = dirname(__DIR__) . '/images/profiles/';
        
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                exit;
            }
        }

        $upload_path = $upload_dir . $filename;
        
        try {
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_picture = $stmt->fetchColumn();

            if ($old_picture) {
                $old_file_path = dirname(__DIR__) . '/' . ltrim($old_picture, '/');
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
        } catch (PDOException $e) {
            error_log("Error deleting old profile picture: " . $e->getMessage());
        }

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            chmod($upload_path, 0644);
            $profile_picture = '/images/profiles/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image. Check directory permissions.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    if ($profile_picture) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$username, $email, $phone, $profile_picture, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$username, $email, $phone, $user_id]);
    }

    if (!empty($current_password) && !empty($new_password)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $user['password'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        if (strlen($new_password) < 8) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
            exit;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
    }

    $pdo->commit();

    $_SESSION['username'] = $username;
    
    $response = [
        'success' => true,
        'message' => 'Profile updated successfully'
    ];

    if ($profile_picture) {
        $response['profile_picture'] = $profile_picture;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in update-profile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in update-profile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}