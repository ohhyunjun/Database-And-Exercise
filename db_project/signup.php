<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* [로그인 페이지와 동일한 스타일 상속 - 기존 디자인 유지] */
        :root {
            --primary: #256CB6; --primary-dark: #1a4a80; --bg-light: #F8F9FA;
            --text-main: #333; --text-sub: #666; --white: #fff; --border: #e1e1e1; --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); display: flex; flex-direction: column; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }

        .navbar { background: var(--white); height: 70px; display: flex; align-items: center; justify-content: flex-start; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 5px; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }
        
        .signup-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 20px; }
        
        .signup-card {
            background: var(--white); width: 100%; max-width: 500px; padding: 40px; border-radius: 12px;
            box-shadow: var(--shadow); border: 1px solid var(--border);
        }
        
        .signup-header { text-align: center; margin-bottom: 30px; }
        .signup-title { font-size: 24px; font-weight: 700; margin-bottom: 10px; color: var(--text-main); }
        .signup-desc { font-size: 14px; color: var(--text-sub); }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; }
        .required { color: #e00; margin-left: 2px; }
        .form-input {
            width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 6px; font-size: 15px; transition: 0.2s;
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1); }

        /* 가입 유형 선택 스타일 */
        .role-selector { display: flex; gap: 10px; background: #f1f3f5; padding: 5px; border-radius: 6px; }
        .role-option { flex: 1; }
        .role-option input { display: none; } 
        .role-option label {
            display: block; text-align: center; padding: 10px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; color: #666; transition: 0.2s;
        }
        .role-option input:checked + label {
            background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid var(--primary);
        }

        .age-wrapper { display: flex; align-items: center; gap: 10px; }
        .age-input { width: 100px; }

        .terms-box {
            background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 20px;
        }
        .check-label { display: flex; align-items: center; font-size: 14px; cursor: pointer; }
        .check-label input { margin-right: 8px; width: 16px; height: 16px; accent-color: var(--primary); }

        .btn-submit {
            width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 6px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { background: var(--primary-dark); }

        .footer { background: #2c3e50; color: #bdc3c7; padding: 40px 0; text-align: center; font-size: 13px; margin-top: auto; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
    </nav>

    <div class="signup-wrapper">
        <div class="signup-card">
            <div class="signup-header">
                <h2 class="signup-title">회원가입</h2>
                <p class="signup-desc">가입 유형을 선택하고 정보를 입력해주세요.</p>
            </div>
            
            <?php if(isset($_GET['error'])): ?>
                <p style="color:red; text-align:center; margin-bottom:15px; background:#fff0f0; padding:10px; border-radius:5px;">
                    ⚠️ <?php echo htmlspecialchars($_GET['error']); ?>
                </p>
            <?php endif; ?>

            <form action="signup_process.php" method="POST">
                
                <div class="form-group">
                    <label class="form-label">가입 유형 선택</label>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" name="role" id="role_user" value="0" checked>
                            <label for="role_user">👤 일반 회원</label>
                        </div>
                        <div class="role-option">
                            <input type="radio" name="role" id="role_admin" value="1">
                            <label for="role_admin">🛡️ 관리자 (승인요청)</label>
                        </div>
                    </div>
                    <p style="font-size:12px; color:#888; margin-top:5px;">
                        ※ 관리자 계정은 가입 후 최고 관리자의 승인이 필요합니다.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">아이디 (username) <span class="required">*</span></label>
                    <input type="text" name="username" class="form-input" placeholder="영문 소문자/숫자" required>
                </div>

                <div class="form-group">
                    <label class="form-label">비밀번호 (password) <span class="required">*</span></label>
                    <input type="password" name="password" class="form-input" placeholder="비밀번호 입력" required>
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호 확인 <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-input" placeholder="비밀번호 재입력" required>
                </div>

                <hr style="margin: 30px 0; border: 0; border-top: 1px dashed #ddd;">

                <div class="form-group">
                    <label class="form-label">이름 (name) <span class="required">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="실명을 입력하세요" required>
                </div>

                <div class="form-group">
                    <label class="form-label">나이 (age) <span class="required">*</span></label>
                    <div class="age-wrapper">
                        <input type="number" name="age" class="form-input age-input" placeholder="세" min="19" required>
                        <span>세 (만 나이 기준)</span>
                    </div>
                    <p style="font-size:12px; color:#888; margin-top:5px;">※ 청년 주택 자격 확인을 위해 필요합니다.</p>
                </div>
                
                <div class="terms-box">
                    <label class="check-label">
                        <input type="checkbox" required>
                        <span>[필수] 개인정보 수집 및 이용에 동의합니다.</span>
                    </label>
                </div>

                <button type="submit" class="btn-submit">가입하기</button>
            </form>
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