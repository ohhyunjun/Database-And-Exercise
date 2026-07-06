<?php
session_start();

// 1. 관리자 권한 체크 (필수)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    echo "<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit();
}

// 2. 삭제할 회원 ID 확인
if (!isset($_GET['id'])) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit();
}

$delete_id = $_GET['id'];

// 본인 삭제 방지 (선택 사항)
if ($delete_id == $_SESSION['user_id']) {
    echo "<script>alert('현재 로그인된 관리자 계정은 삭제할 수 없습니다.'); history.back();</script>";
    exit();
}

// 3. DB 연결
include 'db_conn.php';

if ($conn) {
    // 4. 회원 삭제 쿼리 실행
    // (ON DELETE CASCADE 설정 덕분에 User_Table만 지우면 UserProfile, User_House 등도 자동 삭제됨)
    $sql = "DELETE FROM User_Table WHERE user_id = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $delete_id);

    // 실행
    if (oci_execute($stmt)) {
        // 성공 시
        oci_commit($conn); // 확실하게 저장
        echo "<script>
            alert('회원 정보가 완전히 삭제되었습니다.');
            location.href='admin_users.php';
        </script>";
    } else {
        // 실패 시 에러 메시지
        $e = oci_error($stmt);
        echo "<script>
            alert('삭제 실패: " . addslashes($e['message']) . "');
            history.back();
        </script>";
    }

    oci_free_statement($stmt);
    oci_close($conn);
} else {
    echo "<script>alert('DB 연결 실패'); history.back();</script>";
}
?>