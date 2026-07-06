<?php
session_start();

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    echo "<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit();
}

// 2. 승인할 대상 ID 확인
if (!isset($_GET['id'])) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit();
}

$target_id = $_GET['id'];

// 3. DB 연결
include 'db_conn.php';

if ($conn) {
    // 4. 승인 처리 (USER_ROLE을 2:관리자로 변경)
    // 만약 일반 유저(0)로 승인하려면 숫자만 0으로 바꾸면 됩니다.
    $sql = "UPDATE User_Table SET USER_ROLE = 2 WHERE user_id = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $target_id);

    if (oci_execute($stmt)) {
        oci_commit($conn);
        echo "<script>
            alert('관리자로 승인되었습니다.');
            location.href='admin_users.php?filter=admin'; 
        </script>";
    } else {
        $e = oci_error($stmt);
        echo "<script>
            alert('승인 실패: " . addslashes($e['message']) . "');
            history.back();
        </script>";
    }

    oci_free_statement($stmt);
    oci_close($conn);
} else {
    echo "<script>alert('DB 연결 실패'); history.back();</script>";
}
?>