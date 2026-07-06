<?php
session_start();

// 1. DB 연결 (기존 연결 정보 유지)
include 'db_conn.php';

// 2. 검색 파라미터 및 초기화
$s_type = isset($_GET['type']) ? $_GET['type'] : 'all';       
$s_region = isset($_GET['region']) ? $_GET['region'] : 'all'; 
$s_status = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, active, upcoming, closed
$s_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : ''; 
$s_start = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$s_end = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 3. 사용자 정보 및 로그인 상태
$user_id = $_SESSION['user_id'] ?? null;
$is_login = !empty($user_id);
$username_display = $_SESSION['username'] ?? '';


$housing_types = [];
$regions = [];
$posts = [];

if ($connect) {
    // [Query 1] 필터용 주택 유형
    $stmt = oci_parse($connect, "SELECT * FROM HousingType ORDER BY type_id");
    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $housing_types[] = $row;
    }
    
    // [Query 1-2] 지역 목록
    $stmt = oci_parse($connect, "SELECT * FROM Region ORDER BY region_id");
    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $regions[] = $row;
    }

    // [Query 2] 공고 목록 조회 (★ 좋아요 상태 서브쿼리 추가)
    $select_liked = $is_login 
        ? "(SELECT COUNT(*) FROM USER_HOUSE UH WHERE UH.POST_ID = P.POST_ID AND UH.USER_ID = :user_id_check) AS IS_LIKED," 
        : "'0' AS IS_LIKED,";

    $sql = "SELECT P.post_id, P.post_title, T.type_name, 
               TO_CHAR(P.POST_DATE, 'YYYY-MM-DD') AS POST_DATE,
               TO_CHAR(P.START_DATE, 'YYYY-MM-DD') AS START_DATE,
               TO_CHAR(P.FINISH_DATE, 'YYYY-MM-DD') AS FINISH_DATE,
               MIN(D.monthly_rent) as min_rent,
               MIN(D.deposit) as min_deposit,
               MIN(D.complex_name) as complex_name,
               MIN(D.region_id) as region_id,
               COUNT(DISTINCT D.detail_id) as house_count,
               {$select_liked}
               P.START_DATE AS RAW_START_DATE,
               P.FINISH_DATE AS RAW_FINISH_DATE
        FROM HousingPost P
        JOIN HousingType T ON P.type_id = T.type_id
        LEFT JOIN HouseDetail D ON P.post_id = D.post_id
        WHERE 1=1 "; 

    if ($s_type != 'all') {
        $sql .= " AND P.type_id = :v_type ";
    }
    if ($s_region != 'all') {
        $sql .= " AND D.region_id = :v_region ";
    }
    if (!empty($s_keyword)) {
        $sql .= " AND (P.post_title LIKE '%' || :v_keyword || '%' OR D.complex_name LIKE '%' || :v_keyword || '%') ";
    }
    
    // 상태 필터 로직
    if ($s_status == 'active') {
        $sql .= " AND SYSDATE BETWEEN P.START_DATE AND P.FINISH_DATE ";
    } elseif ($s_status == 'upcoming') {
        $sql .= " AND SYSDATE < P.START_DATE ";
    } elseif ($s_status == 'closed') {
        $sql .= " AND SYSDATE > P.FINISH_DATE ";
    }
    
    if (!empty($s_start)) {
        $sql .= " AND P.POST_DATE >= TO_DATE(:v_start, 'YYYY-MM-DD') ";
    }
    if (!empty($s_end)) {
        $sql .= " AND P.POST_DATE <= TO_DATE(:v_end, 'YYYY-MM-DD') ";
    }

    $sql .= " GROUP BY P.post_id, P.post_title, T.type_name, P.POST_DATE, P.START_DATE, P.FINISH_DATE
            ORDER BY P.post_id DESC";

    $stmt = oci_parse($connect, $sql);

    // 바인딩
    if ($s_type != 'all') oci_bind_by_name($stmt, ':v_type', $s_type);
    if ($s_region != 'all') oci_bind_by_name($stmt, ':v_region', $s_region);
    if (!empty($s_keyword)) oci_bind_by_name($stmt, ':v_keyword', $s_keyword);
    if (!empty($s_start)) oci_bind_by_name($stmt, ':v_start', $s_start);
    if (!empty($s_end)) oci_bind_by_name($stmt, ':v_end', $s_end);
    
    // ★ 로그인 상태일 때만 USER_ID 바인딩
    if ($is_login) oci_bind_by_name($stmt, ':user_id_check', $user_id);

    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
        $posts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공고 목록 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* [세종 블루 디자인 시스템] */
        :root {
            --primary: #256CB6;
            --primary-dark: #1a4a80; 
            --bg-light: #F8F9FA;     
            --text-main: #333;       
            --text-sub: #666;        
            --white: #fff;
            --border: #e1e1e1;
            --success: #28a745;
            --gray: #6c757d;
            --danger: #FF4D4F;
            --warning: #FA8C16; /* 접수예정 색상 */
        }

        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); display: flex; flex-direction: column; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; padding: 0; margin: 0; }

        /* 내비게이션 바 */
        .navbar {
            background: var(--white); height: 70px; 
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 6px; z-index: 2; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }
        .nav-menu { display: flex; gap: 35px; position: absolute; left: 50%; transform: translateX(-50%); }
        .nav-link { font-weight: 600; font-size: 16px; color: #444; padding: 10px 0; transition: 0.2s; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active { color: var(--primary); font-weight: 800; }
        .nav-auth { display: flex; gap: 10px; z-index: 2; align-items: center; }
        .btn-login { border: 1px solid var(--primary); color: var(--primary); background: white; padding: 8px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-login:hover { background: var(--primary); color: white; }
        .user-name { font-weight: 700; color: var(--text-main); font-size: 14px; }

        /* 컨텐츠 레이아웃 */
        .container { max-width: 1400px; width: 95%; margin: 40px auto; flex: 1; }
        .page-header { margin-bottom: 30px; }
        .page-title { font-size: 28px; font-weight: 800; color: #111; margin: 0; }

        /* 검색 패널 */
        .search-panel {
            background: var(--white);
            padding: 28px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 30px;
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-row:last-child {
            margin-bottom: 0;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-sub);
        }

        /* Input Styles */
        .form-select, .form-input, .form-date {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: 0.2s;
            background-color: #fff;
            height: 42px;
        }
        .form-select:focus, .form-input:focus, .form-date:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1);
        }

        .form-select { min-width: 180px; }
        .form-input { min-width: 400px; }
        
        /* Date Range Style */
        .date-range-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-date { min-width: 150px; }
        
        /* Search Button */
        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 32px;
            height: 42px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s;
            margin-left: auto;
        }
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 108, 182, 0.3);
        }

        /* 리스트 스타일 */
        .post-list { display: flex; flex-direction: column; gap: 15px; }

        .post-card {
            background: var(--white); border: 1px solid var(--border); border-radius: 10px;
            padding: 25px; display: flex; align-items: flex-start; justify-content: space-between;
            transition: all 0.2s; cursor: pointer; position: relative; overflow: hidden;
        }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(37, 108, 182, 0.1); border-color: var(--primary); }
        .post-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: #ddd; transition: 0.2s; }
        .post-card:hover::before { background: var(--primary); }
        
        /* ★ 좋아요 버튼 스타일 */
        .btn-like-list {
            background: none; border: none; font-size: 20px; cursor: pointer; color: #ccc;
            position: absolute; right: 15px; top: 15px; z-index: 10; padding: 5px;
            transition: all 0.2s;
        }
        .btn-like-list:hover { color: var(--danger); transform: scale(1.1); }
        .btn-like-list.active { color: var(--danger); }
        
        .post-content { flex: 1; display: flex; flex-direction: column; gap: 12px; } 
        
        .post-badges { display: flex; gap: 8px; align-items: center; }
        .badge-type { background: #E6F2FF; color: var(--primary); padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; }
        .post-id { color: #999; font-size: 13px; }

        .post-title { font-size: 20px; font-weight: 700; color: #222; }
        .post-meta { color: #666; font-size: 14px; display: flex; gap: 20px; align-items: center; }
        
        .post-finance {
            display: flex; gap: 30px; padding-top: 12px; border-top: 1px dashed #eee;
            width: 100%; align-items: center; flex-wrap: wrap; justify-content: flex-start;
        }
        .finance-item { display: flex; flex-direction: column; gap: 2px; padding-right: 30px; border-right: 1px solid #eee; }
        .finance-item:last-child { border-right: none; padding-right: 0; }
        .finance-item.complex { min-width: 150px; } 
        
        .f-label { font-size: 12px; color: #888; font-weight: 500; }
        .f-value { font-size: 15px; font-weight: 700; color: #333; }
        .price-accent { color: var(--primary); font-size: 16px; font-weight: 800; }
        .price-suffix { font-size: 13px; font-weight: 400; color: #666; margin-left: 2px; }

        .post-status { text-align: right; min-width: 120px; margin-left: 20px; align-self: center; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; }
        
        /* 상태별 뱃지 스타일 */
        .status-active { background: #E6FFED; color: var(--success); border: 1px solid #b7eb8f; }
        .status-closed { background: #F5F5F5; color: var(--gray); border: 1px solid #ddd; }
        .status-upcoming { background: #FFF7E6; color: var(--warning); border: 1px solid #FFD591; } /* 접수예정 색상 */
        
        .d-day { color: var(--danger); font-weight: bold; font-size: 13px; margin-top: 5px; display: block; }

        /* 푸터 */
        .footer { background: #2c3e50; color: #bdc3c7; padding: 30px 0; margin-top: auto; text-align: center; font-size: 13px; line-height: 1.6; }
        .footer-links { margin-bottom: 15px; }
        .footer-links a { color: #ccc; margin: 0 10px; text-decoration: none; }
        .footer-links a:hover { color: white; text-decoration: underline; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
        <ul class="nav-menu">
            <li><a href="post_list.php" class="nav-link active">공고목록</a></li>
            <li><a href="map_view.php" class="nav-link">지도보기</a></li>
            <li><a href="mypage.php" class="nav-link">마이페이지</a></li>
        </ul>
        <div class="nav-auth">
            <?php if($is_login): ?>
                <span class="user-name"><?php echo htmlspecialchars($username_display); ?>님</span>
                <button class="btn-login" onclick="location.href='logout.php'">로그아웃</button>
            <?php else: ?>
                <button class="btn-login" onclick="location.href='login.php'">로그인</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2 class="page-title">📋 전체 임대 공고</h2>
        </div>

        <form method="GET" action="post_list.php" class="search-panel">
            
            <div class="filter-row">
                <div class="filter-item">
                    <span class="filter-label">주택 유형</span>
                    <select name="type" class="form-select">
                        <option value="all">전체</option>
                        <?php foreach($housing_types as $t): ?>
                        <option value="<?php echo $t['TYPE_ID']; ?>" <?php echo ($s_type == $t['TYPE_ID']) ? 'selected' : ''; ?>>
                            <?php echo $t['TYPE_NAME']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <span class="filter-label">지역</span>
                    <select name="region" class="form-select">
                        <option value="all">전체 지역</option>
                        <?php foreach($regions as $r): ?>
                        <option value="<?php echo $r['REGION_ID']; ?>" <?php echo ($s_region == $r['REGION_ID']) ? 'selected' : ''; ?>>
                            <?php echo $r['DONG_NAME']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <span class="filter-label">진행 상태</span>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo ($s_status == 'all') ? 'selected' : ''; ?>>전체</option>
                        <option value="upcoming" <?php echo ($s_status == 'upcoming') ? 'selected' : ''; ?>>접수예정</option>
                        <option value="active" <?php echo ($s_status == 'active') ? 'selected' : ''; ?>>접수중</option>
                        <option value="closed" <?php echo ($s_status == 'closed') ? 'selected' : ''; ?>>마감됨</option>
                    </select>
                </div>

                <div class="filter-item">
                    <span class="filter-label">게시일</span>
                    <div class="date-range-box">
                        <input type="date" name="start_date" class="form-date" value="<?php echo $s_start; ?>">
                        <span>~</span>
                        <input type="date" name="end_date" class="form-date" value="<?php echo $s_end; ?>">
                    </div>
                </div>
            </div>

            <div class="filter-row">
                <div class="filter-item" style="flex: 1;">
                    <span class="filter-label">공고명 검색</span>
                    <input type="text" name="keyword" class="form-input" placeholder="지역명, 단지명, 주택유형을 검색해 보세요." value="<?php echo htmlspecialchars($s_keyword); ?>">
                </div>
                <button type="submit" class="btn-search">🔍 검색</button>
            </div>

        </form>

        <div class="post-list">
            <?php if(count($posts) > 0): ?>
                <?php foreach($posts as $p): 
                    // 상태 계산 (SQL이 아닌 PHP에서 정확한 상태 계산)
                    $today_dt = new DateTime();
                    // Oracle에서 가져온 RAW DATE를 사용
                    $start_dt = new DateTime($p['RAW_START_DATE']); 
                    $finish_dt = new DateTime($p['RAW_FINISH_DATE']);
                    
                    $statusClass = '';
                    $statusText = '';
                    $today_dt->setTime(0, 0, 0); $start_dt->setTime(0, 0, 0); $finish_dt->setTime(0, 0, 0);

                    if ($today_dt < $start_dt) {
                        $statusClass = 'status-upcoming';
                        $statusText = '접수예정';
                    } elseif ($today_dt > $finish_dt) {
                        $statusClass = 'status-closed';
                        $statusText = '마감됨';
                    } else {
                        $statusClass = 'status-active';
                        $statusText = '접수중';
                    }
                    
                    // D-Day 계산 (접수중일 때만 표시)
                    $dDayText = "";
                    if($statusText == '접수중') {
                        $diff = $today_dt->diff($finish_dt);
                        $dDayText = "D-" . $diff->days;
                    }
                    
                    // 단지명 표시 로직
                    $complexText = $p['COMPLEX_NAME'] ? $p['COMPLEX_NAME'] : '정보 없음';
                    if ($p['HOUSE_COUNT'] > 1) {
                        $complexText .= " 외 " . ($p['HOUSE_COUNT']-1) . "곳";
                    }
                    
                    // 좋아요 상태
                    $is_liked = ($p['IS_LIKED'] == '1');
                ?>
                <div class="post-card" onclick="location.href='post_detail.php?id=<?php echo $p['POST_ID']; ?>'">
                    
                    <button 
                        class="btn-like-list <?php echo $is_liked ? 'active' : ''; ?>" 
                        onclick="event.stopPropagation(); toggleLike(this, <?php echo $p['POST_ID']; ?>, <?php echo $is_login ? 'true' : 'false'; ?>)"
                    >
                        <?php echo $is_liked ? '♥' : '♡'; ?>
                    </button>
                    
                    <div class="post-content">
                        <div class="post-badges">
                            <span class="badge-type"><?php echo $p['TYPE_NAME']; ?></span> 
                            <span class="post-id">NO. <?php echo $p['POST_ID']; ?></span>
                        </div>
                        <div class="post-title"><?php echo htmlspecialchars($p['POST_TITLE']); ?></div>
                        <div class="post-meta">
                            📅 <?php echo $p['POST_DATE']; ?> | 📝 접수: <?php echo $p['START_DATE']; ?> ~ <?php echo $p['FINISH_DATE']; ?>
                        </div>
                        
                        <div class="post-finance">
                            <div class="finance-item complex">
                                <span class="f-label">단지명</span>
                                <span class="f-value"><?php echo $complexText; ?></span>
                            </div>
                            <?php if($p['MIN_RENT']): ?>
                            <div class="finance-item">
                                <span class="f-label">최저 월임대료</span>
                                <span class="f-value price-accent"><?php echo number_format($p['MIN_RENT']); ?><span class="price-suffix">원~</span></span>
                            </div>
                            <div class="finance-item">
                                <span class="f-label">최저 보증금</span>
                                <span class="f-value"><?php echo number_format($p['MIN_DEPOSIT']); ?><span class="price-suffix">원~</span></span>
                            </div>
                            <?php else: ?>
                            <div class="finance-item">
                                <span class="f-label">정보</span>
                                <span class="f-value">상세보기 참조</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="post-status">
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        <?php if($dDayText): ?>
                        <span class="d-day"><?php echo $dDayText; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:100px 0; color:#999;">
                    <h3>📭 검색된 공고가 없습니다.</h3>
                </div>
            <?php endif; ?>
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
function toggleLike(button, postId, isLoggedIn) {
    if (!isLoggedIn) {
        if (confirm('로그인이 필요합니다. 관심 공고 등록 페이지로 이동하시겠습니까?')) {
            location.href = 'login.php';
        }
        return;
    }

    button.disabled = true;
    const isLiked = button.classList.contains('active');

    const xhr = new XMLHttpRequest();
    // like_process.php는 USER_HOUSE 테이블을 사용해 INSERT/DELETE를 토글합니다.
    xhr.open('POST', 'like_process.php', true); 
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
        button.disabled = false;
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    if (response.action === 'removed' || response.status === 'unliked') {
                        button.classList.remove('active');
                        button.innerHTML = '♡';
                        alert('관심 목록에서 제거되었습니다.');
                    } else {
                        button.classList.add('active');
                        button.innerHTML = '♥';
                        alert('관심 목록에 추가되었습니다!');
                    }
                } else {
                    alert('오류: ' + response.message);
                }
            } catch (e) {
                // JSON 파싱 오류 발생 시 (서버 에러 등)
                alert('서버 처리 오류가 발생했습니다.');
            }
        } else {
            alert('네트워크 통신 오류가 발생했습니다.');
        }
    };
    
    xhr.send('post_id=' + postId);
}
</script>

</body>
</html>

<?php if ($connect) oci_close($connect); ?>