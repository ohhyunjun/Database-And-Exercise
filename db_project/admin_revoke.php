<?php
session_start();

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    echo "<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit();
}

// 2. 해제할 대상 ID 확인
if (!isset($_GET['id'])) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit();
}

$target_id = $_GET['id'];

// 본인 계정 해제 방지
if ($target_id == $_SESSION['user_id']) {
    echo "<script>alert('자기 자신의 관리자 권한은 해제할 수 없습니다.'); history.back();</script>";
    exit();
}

// 3. DB 연결
include 'db_conn.php';

if ($conn) {
    // 4. 권한 해제 (USER_ROLE을 0:일반유저로 변경)
    $sql = "UPDATE User_Table SET USER_ROLE = 0 WHERE user_id = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $target_id);

    if (oci_execute($stmt)) {
        oci_commit($conn);
        echo "<script>
            alert('일반 회원으로 변경되었습니다.');
            location.href='admin_users.php';
        </script>";
    } else {
        $e = oci_error($stmt);
        echo "<script>
            alert('해제 실패: " . addslashes($e['message']) . "');
            history.back();
        </script>";
    }

    oci_free_statement($stmt);
    oci_close($conn);
} else {
    echo "<script>alert('DB 연결 실패'); history.back();</script>";
}
?>