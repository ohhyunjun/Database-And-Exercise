<?php
// 에러 리포팅 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $u_role = $_POST['role']; 
    $u_id = $_POST['username'];
    $u_pw_raw = $_POST['password'];
    $u_pw_conf = $_POST['password_confirm'];
    $u_name = $_POST['name'];
    $u_age = $_POST['age'];

    // 비밀번호 확인
    if ($u_pw_raw !== $u_pw_conf) {
        echo "<script>alert('비밀번호가 일치하지 않습니다.'); history.back();</script>";
        exit;
    }

    if ($conn) {
        $u_pw_hash = password_hash($u_pw_raw, PASSWORD_DEFAULT);

        // =========================================================
        // [Step 1] 계정 생성 (RETURNING 절 제거 -> 충돌 원인 삭제)
        // =========================================================
        $sql1 = "INSERT INTO User_Table (username, password, USER_ROLE) 
                 VALUES (:u_id, :u_pw, :u_role)";
        
        $stmt1 = oci_parse($conn, $sql1);
        oci_bind_by_name($stmt1, ":u_id", $u_id);
        oci_bind_by_name($stmt1, ":u_pw", $u_pw_hash);
        oci_bind_by_name($stmt1, ":u_role", $u_role);

        // 실행 (아직 커밋 안 함)
        if (!oci_execute($stmt1, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmt1);
            if ($e['code'] == 1) { 
                $err_msg = "이미 존재하는 아이디입니다.";
            } else {
                $err_msg = "계정 생성 실패: " . $e['message'];
            }
            echo "<script>alert('" . addslashes($err_msg) . "'); history.back();</script>";
            exit;
        }

        // =========================================================
        // [Step 1.5] 방금 만든 ID 직접 조회 (안전한 방식)
        // =========================================================
        // 같은 세션(트랜잭션) 안이라서 커밋 전에도 조회 가능합니다.
        $sql_get_id = "SELECT user_id FROM User_Table WHERE username = :u_id";
        $stmt_id = oci_parse($conn, $sql_get_id);
        oci_bind_by_name($stmt_id, ":u_id", $u_id);
        
        oci_execute($stmt_id, OCI_NO_AUTO_COMMIT);
        
        $row = oci_fetch_array($stmt_id, OCI_ASSOC);
        
        if (!$row) {
            oci_rollback($conn);
            echo "<script>alert('치명적 오류: 생성된 ID를 찾을 수 없습니다.'); history.back();</script>";
            exit;
        }
        
        $new_user_id = $row['USER_ID']; // 조회된 ID 확보

        // =========================================================
        // [Step 2] 프로필 생성
        // =========================================================
        $sql2 = "INSERT INTO UserProfile (user_id, name, age, is_home, is_married, is_sejong_resident) 
                 VALUES (:u_id_fk, :u_name, :u_age, 0, 0, 0)";
        
        $stmt2 = oci_parse($conn, $sql2);
        oci_bind_by_name($stmt2, ":u_id_fk", $new_user_id);
        oci_bind_by_name($stmt2, ":u_name", $u_name);
        oci_bind_by_name($stmt2, ":u_age", $u_age);

        if (oci_execute($stmt2, OCI_NO_AUTO_COMMIT)) {
            // 모든 단계 성공 시 최종 커밋
            oci_commit($conn);
            echo "<script>alert('가입이 완료되었습니다! 로그인해주세요.'); location.href='login.php';</script>";
        } else {
            // 프로필 실패 시 전체 취소
            oci_rollback($conn);
            $e = oci_error($stmt2);
            echo "<script>alert('프로필 저장 실패: " . addslashes($e['message']) . "'); history.back();</script>";
        }
        
        // 자원 해제
        oci_free_statement($stmt1);
        oci_free_statement($stmt_id);
        oci_free_statement($stmt2);
        oci_close($conn);

    } else {
        echo "<script>alert('DB 연결 실패'); history.back();</script>";
    }
} else {
    header("Location: signup.php");
}
?>