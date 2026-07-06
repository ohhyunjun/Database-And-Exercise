<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* [세종 블루 디자인 시스템] */
        :root {
            --primary: #256CB6;      /* 세종 블루 */
            --primary-dark: #1a4a80; 
            --bg-light: #F8F9FA;     
            --text-main: #333;       
            --text-sub: #666;        
            --white: #fff;
            --border: #e1e1e1;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --danger: #FF4D4F;
        }

        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); display: flex; flex-direction: column; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }

        /* 상단바 */
        .navbar {
            background: var(--white); height: 70px; 
            display: flex; align-items: center; justify-content: flex-start; /* 좌측 정렬 */
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 5px; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }

        /* 로그인 컨테이너 */
        .login-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        
        .login-card {
            background: var(--white); width: 100%; max-width: 400px; padding: 50px 40px; border-radius: 12px;
            box-shadow: var(--shadow); border: 1px solid var(--border); text-align: center;
        }
        
        .login-title { font-size: 26px; font-weight: 800; margin-bottom: 10px; color: var(--primary); }
        .login-desc { font-size: 14px; color: var(--text-sub); margin-bottom: 30px; }

        /* 입력 필드 스타일 */
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-input {
            width: 100%; padding: 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 15px; transition: 0.2s;
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1); }

        /* 로그인 버튼 */
        .btn-submit {
            width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 108, 182, 0.3); }

        /* 하단 링크 */
        .login-links { margin-top: 25px; font-size: 13px; color: var(--text-sub); display: flex; justify-content: center; gap: 15px; }
        .login-links a { transition: 0.2s; }
        .login-links a:hover { text-decoration: underline; color: var(--primary); font-weight: 600; }
        .divider { color: #ddd; }

        /* 에러 메시지 박스 */
        .error-box {
            background-color: #FFF1F0; border: 1px solid #FFA39E; color: #D8000C;
            padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; text-align: left;
        }

        /* 푸터 */
        .footer { background: #2c3e50; color: #bdc3c7; padding: 40px 0; text-align: center; font-size: 13px; margin-top: auto; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
    </nav>

    <div class="login-wrapper">
        <div class="login-card">
            <h2 class="login-title">로그인</h2>
            <p class="login-desc">세종 청년주택 서비스에 오신 것을 환영합니다.</p>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="error-box">⚠️ <?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <div class="form-group">
                    <label class="form-label">아이디</label>
                    <input type="text" name="username" class="form-input" placeholder="아이디를 입력하세요" required>
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호</label>
                    <input type="password" name="password" class="form-input" placeholder="비밀번호를 입력하세요" required>
                </div>
                <button type="submit" class="btn-submit">로그인</button>
            </form>

            <div class="login-links">
                <a href="signup.php">회원가입</a>
                <span class="divider">|</span>
                <a href="#">아이디 찾기</a>
                <span class="divider">|</span>
                <a href="#">비밀번호 찾기</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>
            (30016) 세종특별자치시 조치원읍 세종로 2639 홍익대학교 세종캠퍼스<br>
            PROJECT TEAM 6<br>
            Copyright © 2025 Sejong Youth Housing. All rights reserved.
        </p>
    </footer>

</body>
</html>