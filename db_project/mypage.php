<?php
session_start();

// 1. 로그인 체크
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. DB 연결
include 'db_conn.php';
if (!$conn) die("DB 연결 실패");

// ---------------------------------------------------------
// [Logic 1] 프로필 수정 (POST 처리)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    
    $p_age = $_POST['age'];
    $p_is_married = $_POST['is_married'];
    $p_is_home = $_POST['is_home'];
    $p_is_sejong = $_POST['is_sejong_resident'];
    $p_region = ($p_is_sejong == '1' && !empty($_POST['current_region_id'])) ? $_POST['current_region_id'] : null;
    $p_car = !empty($_POST['car_limit']) ? $_POST['car_limit'] : 0;
    $p_deposit = !empty($_POST['desired_deposit']) ? $_POST['desired_deposit'] : 0;
    $p_rent = !empty($_POST['desired_monthly_rent']) ? $_POST['desired_monthly_rent'] : 0;

    $sql_upd = "UPDATE UserProfile SET 
                age = :age, is_married = :is_married, is_home = :is_home,
                is_sejong_resident = :is_sejong, current_region_id = :region_id,
                car_limit = :car_limit, desired_deposit = :deposit, desired_monthly_rent = :rent
                WHERE user_id = :bind_id";
                
    $stmt_upd = oci_parse($conn, $sql_upd);
    
    oci_bind_by_name($stmt_upd, ':age', $p_age);
    oci_bind_by_name($stmt_upd, ':is_married', $p_is_married);
    oci_bind_by_name($stmt_upd, ':is_home', $p_is_home);
    oci_bind_by_name($stmt_upd, ':is_sejong', $p_is_sejong);
    oci_bind_by_name($stmt_upd, ':region_id', $p_region);
    oci_bind_by_name($stmt_upd, ':car_limit', $p_car);
    oci_bind_by_name($stmt_upd, ':deposit', $p_deposit);
    oci_bind_by_name($stmt_upd, ':rent', $p_rent);
    oci_bind_by_name($stmt_upd, ':bind_id', $user_id);
    
    if (oci_execute($stmt_upd)) {
        oci_commit($conn);
        echo "<script>alert('정보가 성공적으로 수정되었습니다.'); location.href='mypage.php';</script>";
        exit;
    } else {
        $e = oci_error($stmt_upd);
        echo "<script>alert('수정 실패: " . addslashes($e['message']) . "'); history.back();</script>";
        exit;
    }
}

// ---------------------------------------------------------
// [Logic 2] 데이터 조회
// ---------------------------------------------------------

// 2-1. 내 프로필
$sql_prof = "SELECT UP.*, U.username 
             FROM UserProfile UP 
             JOIN User_Table U ON UP.user_id = U.user_id 
             WHERE UP.user_id = :my_id"; 
$stmt_prof = oci_parse($conn, $sql_prof);
oci_bind_by_name($stmt_prof, ':my_id', $user_id);
oci_execute($stmt_prof);
$my_profile = oci_fetch_array($stmt_prof, OCI_ASSOC);

if (!$my_profile) {
    echo "<script>alert('프로필 정보를 찾을 수 없습니다.'); location.href='login.php';</script>";
    exit;
}
$my_profile = array_change_key_case($my_profile, CASE_UPPER);

// 2-2. 지역 목록
$regions = [];
$sql_reg = "SELECT region_id, dong_name FROM Region ORDER BY region_id";
$stmt_reg = oci_parse($conn, $sql_reg);
oci_execute($stmt_reg);
while($row = oci_fetch_array($stmt_reg, OCI_ASSOC)) {
    $regions[] = array_change_key_case($row, CASE_UPPER);
}

// 2-3. 관심 공고
$favorite_posts = [];
$sql_fav = "SELECT P.post_id, P.post_title, T.type_name, 
           TO_CHAR(P.START_DATE, 'YYYY-MM-DD') AS START_DATE,
           TO_CHAR(P.FINISH_DATE, 'YYYY-MM-DD') AS FINISH_DATE,
           MIN(D.monthly_rent) as min_rent, MIN(D.deposit) as min_deposit,
           MIN(D.complex_name) as complex_name, COUNT(DISTINCT D.detail_id) as house_count
    FROM HousingPost P
    JOIN User_House UH ON P.post_id = UH.post_id
    JOIN HousingType T ON P.type_id = T.type_id
    LEFT JOIN HouseDetail D ON P.post_id = D.post_id
    WHERE UH.user_id = :my_id
    GROUP BY P.post_id, P.post_title, T.type_name, P.START_DATE, P.FINISH_DATE
    ORDER BY P.post_id DESC";
$stmt_fav = oci_parse($conn, $sql_fav);
oci_bind_by_name($stmt_fav, ':my_id', $user_id);
oci_execute($stmt_fav);
while ($row = oci_fetch_array($stmt_fav, OCI_ASSOC)) {
    $favorite_posts[] = array_change_key_case($row, CASE_UPPER);
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>마이페이지 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================================
           [1. MAIN PAGE (main.php) 원본 스타일 - 100% 유지]
           ========================================================================== */
        :root {
            --primary: #256CB6;      /* 메인 컬러 */
            --primary-dark: #1a4a80; /* 호버 컬러 */
            --accent: #00C73C;       /* 강조 */
            --bg-light: #F8F9FA;     /* 배경색 */
            --text-main: #333;       /* 본문 텍스트 */
            --text-sub: #666;        /* 보조 텍스트 */
            --white: #fff;
            --border: #e1e1e1;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --danger: #FF4D4F;       /* 경고 */
        }

        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); display: flex; flex-direction: column; min-height: 100vh; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        ul { list-style: none; padding: 0; margin: 0; }

        /* Navbar */
        .navbar {
            background: var(--white); height: 70px; 
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 6px; z-index: 2; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }
        
        .nav-menu { 
            display: flex; gap: 35px; 
            position: absolute; left: 50%; transform: translateX(-50%);
        }
        .nav-link { font-weight: 600; font-size: 16px; color: #444; padding: 10px 0; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active { color: var(--primary); font-weight: 800; }
        
        .nav-auth { display: flex; gap: 10px; z-index: 2; align-items: center; }
        .btn-login { 
            border: 1px solid var(--primary); color: var(--primary); background: white;
            padding: 8px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-login:hover { background: var(--primary); color: white; }
        .user-name { font-weight: 700; color: var(--text-main); font-size: 14px; }

        /* Footer */
        .footer { background: #2c3e50; color: #bdc3c7; padding: 30px 0; margin-top: auto; text-align: center; font-size: 13px; line-height: 1.6; }
        .footer-links { margin-bottom: 15px; }
        .footer-links a { color: #ccc; margin: 0 10px; text-decoration: none; }
        .footer-links a:hover { color: white; text-decoration: underline; }

        /* ==========================================================================
           [2. 마이페이지 와이드(Wide) 스타일]
           ========================================================================== */
        
        .container { 
            max-width: 1600px; width: 95%; margin: 40px auto; 
            flex: 1;
        }
        
        .page-header { margin-bottom: 40px; padding-left: 10px; border-left: 5px solid var(--primary); }
        .page-title { font-size: 32px; font-weight: 800; margin-bottom: 5px; color: #222; letter-spacing: -1px; line-height: 1; }
        .page-desc { font-size: 15px; color: #666; margin-left: 5px; }

        /* 섹션 박스 */
        .section-card { 
            background: var(--white); border-radius: 16px; border: 1px solid var(--border); 
            padding: 40px; margin-bottom: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .section-title { font-size: 22px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }

        /* [Wide Form Grid] */
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 30px; 
            align-items: start;
        }
        
        .col-span-2 { grid-column: span 2; }
        .col-span-4 { grid-column: span 4; }

        .form-group { margin-bottom: 5px; display: flex; flex-direction: column; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: #555; margin-bottom: 8px; }
        .form-input { 
            width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 8px; 
            font-size: 15px; transition: 0.2s; background: #fff; height: 50px;
        }
        .form-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1); }
        .form-input:read-only { background-color: #f8f9fa; color: #888; cursor: default; border-color: #eee; }
        .form-input:disabled { background-color: #f1f3f5; color: #aaa; cursor: not-allowed; }

        .radio-group { 
            display: flex; gap: 20px; background: #fff; padding: 0 15px; 
            border-radius: 8px; border: 1px solid #ddd; height: 50px; align-items: center;
        }
        .radio-label { display: flex; align-items: center; cursor: pointer; font-size: 15px; font-weight: 500; color: #444; }
        .radio-label input { margin-right: 8px; accent-color: var(--primary); width: 18px; height: 18px; cursor: pointer; }

        .form-divider { grid-column: span 4; height: 1px; background: #eee; margin: 10px 0; }
        .form-subtitle { grid-column: span 4; font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: -10px; margin-top: 10px; }

        .btn-submit { 
            background: var(--primary); color: white; padding: 16px 60px; border: none; border-radius: 8px; 
            font-size: 18px; font-weight: 700; cursor: pointer; transition: 0.2s; display: block; margin: 40px auto 0;
            box-shadow: 0 4px 12px rgba(37, 108, 182, 0.3);
        }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 108, 182, 0.4); }

        /* [Wide Post Grid] */
        .post-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 30px; 
        }
        
        .post-card { 
            border: 1px solid #e1e1e1; border-radius: 12px; padding: 30px; 
            transition: 0.2s; cursor: pointer; background: white; position: relative;
            display: flex; flex-direction: column; height: 100%;
        }
        .post-card:hover { border-color: var(--primary); box-shadow: 0 8px 25px rgba(37, 108, 182, 0.15); transform: translateY(-5px); }
        
        .card-badge { 
            display: inline-block; background: #E6F2FF; color: var(--primary); 
            padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; margin-bottom: 15px; width: fit-content;
        }
        
        /* 제목 수정: 커서 및 호버 효과 추가 */
        .card-title { 
            font-size: 20px; font-weight: 800; color: #222; margin-bottom: 15px; line-height: 1.4; flex: 1; 
            cursor: pointer;
        }
        .card-title:hover { color: var(--primary); }

        .card-date { color: #888; font-size: 14px; margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 15px; }
        
        .card-info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 15px; }
        .card-label { color: #666; font-weight: 500; }
        .card-val { font-weight: 700; color: #333; }
        .card-val.price { color: var(--primary); }

        .empty-state { text-align: center; padding: 100px 0; color: #999; background: #f9f9f9; border-radius: 12px; grid-column: span 3; }

        /* [추가된 CSS] 삭제 버튼 스타일 */
        .btn-remove {
            width: 100%;
            padding: 10px;
            background: white;
            border: 2px solid var(--danger);
            color: var(--danger);
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 15px;
        }
        .btn-remove:hover {
            background: var(--danger);
            color: white;
        }
        .btn-remove:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
        <ul class="nav-menu">
            <li><a href="post_list.php" class="nav-link">공고목록</a></li>
            <li><a href="map_view.php" class="nav-link">지도보기</a></li>
            <li><a href="mypage.php" class="nav-link active">마이페이지</a></li>
        </ul>
        <div class="nav-auth">
            <span class="user-name" style="margin-right:10px;"><?php echo htmlspecialchars($_SESSION['username']); ?>님</span>
            <button class="btn-login" onclick="location.href='logout.php'">로그아웃</button>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">마이페이지</h1>
            <span class="page-desc">내 정보를 수정하고 관심 공고를 관리하세요.</span>
        </div>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">🛠️ 프로필 정보 수정</span>
            </div>
            
            <form action="mypage.php" method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-grid">
                    
                    <div class="form-subtitle">기본 정보</div>
                    
                    <div class="form-group">
                        <label class="form-label">아이디 (수정불가)</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($my_profile['USERNAME']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">이름 (수정불가)</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($my_profile['NAME']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">나이</label>
                        <input type="number" name="age" class="form-input" value="<?php echo $my_profile['AGE']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">결혼 여부</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="is_married" value="0" <?php echo ($my_profile['IS_MARRIED'] == 0) ? 'checked' : ''; ?>> 미혼
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="is_married" value="1" <?php echo ($my_profile['IS_MARRIED'] == 1) ? 'checked' : ''; ?>> 기혼
                            </label>
                        </div>
                    </div>

                    <div class="form-divider"></div>

                    <div class="form-subtitle">거주 및 자산 정보</div>

                    <div class="form-group">
                        <label class="form-label">주택 소유 여부</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="is_home" value="0" <?php echo ($my_profile['IS_HOME'] == 0) ? 'checked' : ''; ?>> 무주택
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="is_home" value="1" <?php echo ($my_profile['IS_HOME'] == 1) ? 'checked' : ''; ?>> 유주택
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">세종시 거주 여부</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="is_sejong_resident" value="1" <?php echo ($my_profile['IS_SEJONG_RESIDENT'] == 1) ? 'checked' : ''; ?> onclick="toggleRegion(true)"> 예
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="is_sejong_resident" value="0" <?php echo ($my_profile['IS_SEJONG_RESIDENT'] == 0) ? 'checked' : ''; ?> onclick="toggleRegion(false)"> 아니오
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">현재 거주 지역 (세종시 거주 시 선택)</label>
                        <select name="current_region_id" id="regionSelect" class="form-input" <?php echo ($my_profile['IS_SEJONG_RESIDENT'] == 0) ? 'disabled' : ''; ?>>
                            <option value="">-- 동 선택 --</option>
                            <?php foreach($regions as $r): ?>
                            <option value="<?php echo $r['REGION_ID']; ?>" <?php echo ($my_profile['CURRENT_REGION_ID'] == $r['REGION_ID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['DONG_NAME']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">보유 차량가액 (만원)</label>
                        <input type="number" name="car_limit" class="form-input" value="<?php echo $my_profile['CAR_LIMIT']; ?>" placeholder="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">희망 보증금 (만원)</label>
                        <input type="number" name="desired_deposit" class="form-input" value="<?php echo $my_profile['DESIRED_DEPOSIT']; ?>" placeholder="희망 금액 입력">
                    </div>
                    <div class="form-group">
                        <label class="form-label">희망 월세 (만원)</label>
                        <input type="number" name="desired_monthly_rent" class="form-input" value="<?php echo $my_profile['DESIRED_MONTHLY_RENT']; ?>" placeholder="희망 금액 입력">
                    </div>
                    <div class="form-group"></div> </div>

                <button type="submit" class="btn-submit">정보 수정 저장</button>
            </form>
        </div>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">❤️ 관심 공고 (<?php echo count($favorite_posts); ?>건)</span>
                <a href="post_list.php" style="font-size:14px; color:#666; font-weight:600;">+ 공고 더보기</a>
            </div>

            <div class="post-grid">
                <?php if(empty($favorite_posts)): ?>
                    <div class="empty-state">
                        <h3>💔 아직 관심 등록한 공고가 없습니다</h3>
                        <p style="margin-top:10px;">마음에 드는 공고를 찾아 관심 등록해보세요!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($favorite_posts as $post): 
                        $complex = $post['COMPLEX_NAME'] ? htmlspecialchars($post['COMPLEX_NAME']) : '정보 없음';
                        if($post['HOUSE_COUNT'] > 1) $complex .= " 외 " . ($post['HOUSE_COUNT']-1) . "곳";
                        
                        // 주택유형 뱃지 색상
                        $badgeStyle = "";
                        if(strpos($post['TYPE_NAME'], '행복') !== false) $badgeStyle = "background:#e6ffed; color:#00C73C;";
                    ?>
                    <div class="post-card" id="card-<?php echo $post['POST_ID']; ?>">
                        <div class="card-badge" style="<?php echo $badgeStyle; ?>"><?php echo htmlspecialchars($post['TYPE_NAME']); ?></div>
                        
                        <div class="card-title" onclick="location.href='post_detail.php?id=<?php echo $post['POST_ID']; ?>'">
                            <?php echo htmlspecialchars($post['POST_TITLE']); ?>
                        </div>
                        
                        <div class="card-date">📅 접수: <?php echo $post['START_DATE']; ?> ~ <?php echo $post['FINISH_DATE']; ?></div>
                        
                        <div class="card-info-row">
                            <span class="card-label">단지명</span>
                            <span class="card-val"><?php echo $complex; ?></span>
                        </div>
                        <?php if(isset($post['MIN_DEPOSIT'])): ?>
                        <div class="card-info-row">
                            <span class="card-label">보증금</span>
                            <span class="card-val price"><?php echo number_format($post['MIN_DEPOSIT']); ?>원~</span>
                        </div>
                        <div class="card-info-row">
                            <span class="card-label">월세</span>
                            <span class="card-val price"><?php echo number_format($post['MIN_RENT']); ?>원~</span>
                        </div>
                        <?php endif; ?>

                        <button class="btn-remove" onclick="removeLike(<?php echo $post['POST_ID']; ?>, event)">
                            ❌ 관심 해제
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-links">
            <a href="#">이용약관</a> | <a href="#"><b>개인정보처리방침</b></a> | <a href="#">고객센터</a>
        </div>
        <p>
            (30016) 세종특별자치시 조치원읍 세종로 2639 홍익대학교 세종캠퍼스<br>
            PROJECT TEAM 6 (강경한, 김강표, 오현준, 김동혁)<br>
            Copyright © 2025 Sejong Youth Housing. All rights reserved.
        </p>
    </footer>

    <script>
    function toggleRegion(isResident) {
        const regionSelect = document.getElementById('regionSelect');
        if (isResident) {
            regionSelect.disabled = false;
        } else {
            regionSelect.disabled = true;
            regionSelect.value = ""; // 선택 초기화
        }
    }

    // [추가된 함수] 관심 공고 삭제 기능
    function removeLike(postId, event) {
        event.stopPropagation(); // 카드 클릭 이벤트 전파 방지
        
        if (!confirm('이 공고를 관심 목록에서 제거하시겠습니까?')) {
            return;
        }
        
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = '처리 중...';
        
        // AJAX 요청
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'like_process.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // 성공 시 카드 제거 애니메이션
                        const card = document.getElementById('card-' + postId);
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        
                        setTimeout(() => {
                            card.remove();
                            
                            // 남은 공고가 없으면 빈 상태 표시 (새로고침)
                            const remainingCards = document.querySelectorAll('.post-card');
                            if (remainingCards.length === 0) {
                                location.reload();
                            }
                        }, 300);
                        
                        alert(response.message);
                    } else {
                        alert('오류: ' + response.message);
                        btn.disabled = false;
                        btn.textContent = '❌ 관심 해제';
                    }
                } catch(e) {
                    alert('처리 중 오류가 발생했습니다.');
                    console.error(e);
                    btn.disabled = false;
                    btn.textContent = '❌ 관심 해제';
                }
            }
        };
        
        xhr.onerror = function() {
            alert('네트워크 오류가 발생했습니다.');
            btn.disabled = false;
            btn.textContent = '❌ 관심 해제';
        };
        
        xhr.send('post_id=' + postId);
    }
    </script>

</body>
</html>

<?php if ($conn) oci_close($conn); ?>