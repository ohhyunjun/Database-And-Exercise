<?php
session_start();
include 'db_conn.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $u_id = $_POST['username'];
    $u_pw = $_POST['password'];

    if ($conn) {
        // 1. 아이디로 사용자 정보 조회
        $sql = "SELECT user_id, username, password, USER_ROLE FROM User_Table WHERE username = :u_id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":u_id", $u_id);
        oci_execute($stmt);
        
        if ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
            // 2. 비밀번호 검증
            // signup.php에서 password_hash로 암호화했으므로 password_verify 사용
            if (password_verify($u_pw, $row['PASSWORD'])) {
                
                // 3. 세션 저장
                $_SESSION['user_id'] = $row['USER_ID'];
                $_SESSION['username'] = $row['USERNAME'];
                $_SESSION['role'] = $row['USER_ROLE']; // 0:유저, 1:대기, 2:관리자

                // 4. 권한별 리다이렉트
                if ($row['USER_ROLE'] == 2) {
                    // [관리자] 대시보드로 이동
                    header("Location: admin_main.php");
                } elseif ($row['USER_ROLE'] == 1) {
                    // [승인 대기] 로그인 차단
                    $error_msg = "관리자 승인 대기 중인 계정입니다. 승인 후 이용해주세요.";
                    session_destroy(); // 세션 파기
                    header("Location: login.php?error=" . urlencode($error_msg));
                } else {
                    // [일반 유저] 메인으로 이동
                    header("Location: main.php");
                }
                exit();

            } else {
                // 비밀번호 불일치
                header("Location: login.php?error=" . urlencode("비밀번호가 일치하지 않습니다."));
                exit();
            }
        } else {
            // 아이디 없음
            header("Location: login.php?error=" . urlencode("존재하지 않는 아이디입니다."));
            exit();
        }
    } else {
        // DB 연결 실패
        header("Location: login.php?error=" . urlencode("DB 연결에 실패했습니다."));
        exit();
    }
} else {
    // 잘못된 접근
    header("Location: login.php");
    exit();
}
?>