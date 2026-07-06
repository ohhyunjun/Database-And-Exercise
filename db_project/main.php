<?php
session_start();

// 1. DB 연결
include 'db_conn.php';

// 2. 데이터 담을 배열 초기화
$data = [
    'new_posts' => [],  // 오늘 등록된 새로운 공고
    'cheapest' => [],   // 가성비 주택
    'deadline' => []    // 마감 임박
];

if ($conn) {
    // -------------------------------------------------------------
    // [Query 1] 오늘 등록된 새로운 공고 (최신순 4개)
    // -------------------------------------------------------------
    // 상단 카드는 동 이름을 뺀 상태 유지 (전용면적만 표시)
    $sql = "SELECT P.post_id, P.post_title, T.type_name, 
               D.deposit, D.monthly_rent, D.AREA AS EXCLUSIVE_AREA
            FROM HousingPost P
            JOIN HousingType T ON P.type_id = T.type_id
            JOIN HouseDetail D ON P.post_id = D.post_id
            ORDER BY P.post_id DESC 
            FETCH FIRST 4 ROWS ONLY";
            
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $data['new_posts'][] = $row;
    }

    // -------------------------------------------------------------
    // [Query 2] 가성비 주택 (월세 낮은 순 TOP 3)
    // -------------------------------------------------------------
    // ★수정: 지역(동) 정보를 다시 가져오도록 JOIN Region 복구
    $sql = "SELECT P.post_id, D.complex_name, R.dong_name, D.monthly_rent, D.deposit
            FROM HouseDetail D
            JOIN HousingPost P ON D.post_id = P.post_id
            JOIN Region R ON D.region_id = R.region_id
            ORDER BY D.monthly_rent ASC 
            FETCH FIRST 3 ROWS ONLY";
            
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $data['cheapest'][] = $row;
    }

    // -------------------------------------------------------------
    // [Query 3] 마감 임박 (남은 날짜 순 TOP 3)
    // -------------------------------------------------------------
    $sql = "SELECT post_id, post_title, TO_CHAR(FINISH_DATE, 'YYYY.MM.DD') AS FINISH_DATE, 
               CEIL(FINISH_DATE - SYSDATE) AS D_DAY
        FROM HousingPost
        WHERE FINISH_DATE >= SYSDATE
        ORDER BY FINISH_DATE ASC 
        FETCH FIRST 3 ROWS ONLY";
            
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $data['deadline'][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>세종 청년주택 - 당신의 보금자리를 찾으세요</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #256CB6;      
            --primary-dark: #1a4a80; 
            --accent: #00C73C;       
            --bg-light: #F8F9FA;     
            --text-main: #333;       
            --text-sub: #666;        
            --white: #fff;
            --border: #e1e1e1;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --danger: #FF4D4F;       
        }

        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        ul { list-style: none; padding: 0; margin: 0; }

        /* === [1. 내비게이션 바] === */
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
        
        .nav-auth { display: flex; gap: 10px; z-index: 2; align-items: center; }
        .btn-login { 
            border: 1px solid var(--primary); color: var(--primary); background: white;
            padding: 8px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-login:hover { background: var(--primary); color: white; }
        
        .user-name { font-weight: 700; color: var(--text-main); font-size: 14px; }

        /* === [2. 히어로 섹션] === */
        .hero {
            background: linear-gradient(rgba(37, 108, 182, 0.85), rgba(26, 74, 128, 0.9)), url('https://www.sejong.go.kr/images/kor/sub/history_img04.jpg') no-repeat center/cover;
            height: 420px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: white;
        }
        .hero h1 { font-size: 38px; margin-bottom: 12px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); letter-spacing: -1px; }
        .hero p { font-size: 17px; margin-bottom: 40px; opacity: 0.9; font-weight: 300; }

        .search-box {
            background: white; padding: 8px; border-radius: 50px; display: flex; width: 100%; max-width: 640px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25); align-items: center;
        }
        .search-input { border: none; flex: 1; padding: 14px 24px; font-size: 16px; outline: none; border-radius: 30px; }
        
        .btn-search {
            background: transparent; color: var(--primary); border: 2px solid var(--primary); width: 48px; height: 48px; border-radius: 50%;
            font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; margin-right: 5px;
        }
        .btn-search:hover { background: var(--primary); color: white; transform: scale(1.05); }

        /* === [3. 메인 컨텐츠] === */
        .container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
        
        .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        .section-title { font-size: 22px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .section-more { font-size: 13px; color: var(--text-sub); font-weight: 500; }
        .section-more:hover { color: var(--primary); text-decoration: underline; }

        /* 카드 그리드 */
        .card-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        
        .house-card {
            background: var(--white); border-radius: 12px; overflow: hidden; border: 1px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; position: relative; display: flex; flex-direction: column;
        }
        .house-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); border-color: var(--primary); }
        
        .card-img { 
            height: 170px; background-color: #e9ecef; position: relative; 
            display: flex; align-items: center; justify-content: center; font-size: 48px; color: #adb5bd;
        }
        .badge { position: absolute; top: 15px; left: 15px; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .bg-1 { background: var(--primary); }
        .bg-2 { background: var(--accent); }
        .bg-3 { background: #6c757d; }

        .like-btn {
            position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.9);
            border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; cursor: pointer; border: none; color: #ccc; transition: 0.2s;
        }
        .like-btn:hover { color: var(--danger); transform: scale(1.1); }
        .like-btn.active { color: var(--danger); }

        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .card-title { font-size: 17px; font-weight: 700; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-info { color: var(--text-sub); font-size: 13px; margin-bottom: 16px; font-weight: 500; }
        .card-price { font-size: 16px; font-weight: 800; color: var(--text-main); }
        .card-price span { font-size: 12px; font-weight: 400; color: #888; margin-left: 4px; }
        .deposit { color: var(--primary); }

        /* 하단 스플릿 섹션 */
        .split-row { display: flex; gap: 30px; margin-top: 60px; align-items: stretch; }
        .split-box { flex: 1; background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
        
        .comm-list { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }

        .list-item { 
            display: flex; align-items: center; padding: 15px 10px; border-bottom: 1px solid #f1f1f1; 
            cursor: pointer; transition: 0.2s; border-radius: 8px; height: 72px;
        }
        .list-item:last-child { border-bottom: none; }
        .list-item:hover { background-color: #f8fbff; }
        
        .rank-num { 
            font-size: 22px; width: 40px; font-weight: 900; color: var(--primary); 
            text-align: center; margin-right: 15px; font-style: italic;
        }
        .list-content { flex: 1; min-width: 0; padding-right: 10px; }
        .list-title { 
            font-weight: 700; font-size: 15px; margin-bottom: 4px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
        }
        .list-sub { font-size: 13px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .list-right { text-align: right; min-width: 120px; flex-shrink: 0; }
        .price-highlight { color: var(--accent); font-weight: 800; font-size: 15px; }
        
        .d-day { 
            background: #FFF1F0; color: var(--danger); font-size: 12px; padding: 3px 8px; 
            border-radius: 4px; font-weight: 700; display: inline-block; margin-bottom: 4px;
        }

        /* 푸터 */
        .footer { background: #2c3e50; color: #bdc3c7; padding: 40px 0; margin-top: 80px; text-align: center; font-size: 13px; line-height: 1.6; }
        .footer-links { margin-bottom: 15px; }
        .footer-links a { color: #ccc; margin: 0 10px; text-decoration: none; }
        .footer-links a:hover { color: white; text-decoration: underline; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
        
        <ul class="nav-menu">
            <li><a href="post_list.php" class="nav-link">공고목록</a></li>
            <li><a href="map_view.php" class="nav-link">지도보기</a></li>
            <li><a href="mypage.php" class="nav-link">마이페이지</a></li>
        </ul>
        
        <div class="nav-auth">
            <?php if(isset($_SESSION['username'])): ?>
                <span class="user-name"><?php echo $_SESSION['username']; ?>님</span>
                <button class="btn-login" onclick="location.href='logout.php'">로그아웃</button>
            <?php else: ?>
                <button class="btn-login" onclick="location.href='login.php'">로그인</button>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <h1>세종시 청년 주택의 모든 것</h1>
        <p>나에게 딱 맞는 임대주택 공고를 한눈에 비교하고 분석하세요.</p>
        
        <form action="post_list.php" method="GET" class="search-box">
            <input type="text" name="keyword" class="search-input" placeholder="지역명, 단지명, 주택유형을 검색하세요">
            <button type="submit" class="btn-search">🔍</button>
        </form>
    </section>

    <div class="container">
        
        <div class="section-header">
            <div class="section-title">✨ 오늘 등록된 새로운 공고</div>
            <a href="post_list.php" class="section-more">더보기 ></a>
        </div>
        
        <div class="card-grid">
            <?php if(count($data['new_posts']) > 0): ?>
                <?php foreach($data['new_posts'] as $item): 
                    $badgeClass = 'bg-3'; 
                    if(strpos($item['TYPE_NAME'], '국민') !== false) $badgeClass = 'bg-1';
                    if(strpos($item['TYPE_NAME'], '행복') !== false) $badgeClass = 'bg-2';
                ?>
                <div class="house-card" onclick="location.href='post_detail.php?id=<?php echo $item['POST_ID']; ?>'">
                    <div class="card-img">
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $item['TYPE_NAME']; ?></span>
                        🏠
                        <button class="like-btn" onclick="event.stopPropagation(); location.href='like_process.php?id=<?php echo $item['POST_ID']; ?>'">♡</button>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($item['POST_TITLE']); ?></div>
                        <div class="card-info">전용 <?php echo $item['EXCLUSIVE_AREA']; ?>㎡</div>
                        <div class="card-price"><?php echo number_format($item['DEPOSIT']); ?> <span>(보증금)</span></div>
                        <div class="card-price"><?php echo number_format($item['MONTHLY_RENT']); ?> <span>(월세)</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:span 4; text-align:center; padding:40px; color:#999;">오늘 등록된 공고가 없습니다.</div>
            <?php endif; ?>
        </div>

        <div class="split-row">
            
            <div class="split-box">
                <div class="section-header" style="border:none; margin-bottom:5px;">
                    <div class="section-title" style="font-size:18px;">💸 월세 부담 없는 가성비 주택</div>
                    <a href="post_list.php" class="section-more">+ 더보기</a>
                </div>
                <ul class="comm-list">
                    <?php $rank=1; foreach($data['cheapest'] as $item): ?>
                    <li class="list-item" onclick="location.href='post_detail.php?id=<?php echo $item['POST_ID']; ?>'">
                        <div class="rank-num"><?php echo $rank++; ?></div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($item['COMPLEX_NAME']); ?></div>
                            <div class="list-sub"><?php echo $item['DONG_NAME']; ?></div>
                        </div>
                        <div class="list-right">
                            <div class="price-highlight">월 <?php echo number_format($item['MONTHLY_RENT']); ?>원</div>
                            <div class="list-sub">보증금 <?php echo number_format($item['DEPOSIT']/10000); ?>만</div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="split-box">
                <div class="section-header" style="border:none; margin-bottom:5px;">
                    <div class="section-title" style="font-size:18px;">⏰ 마감 임박! 서두르세요</div>
                    <a href="post_list.php" class="section-more">+ 더보기</a>
                </div>
                <ul class="comm-list">
                    <?php foreach($data['deadline'] as $item): ?>
                    <li class="list-item" onclick="location.href='post_detail.php?id=<?php echo $item['POST_ID']; ?>'">
                        <div class="list-content">
                            <div class="d-day">D-<?php echo $item['D_DAY']; ?></div>
                            <div class="list-title" style="font-size:14px;"><?php echo htmlspecialchars($item['POST_TITLE']); ?></div>
                        </div>
                        <div class="list-right">
                            <div class="list-sub">~ <?php echo $item['FINISH_DATE']; ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
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

</body>
</html>

<?php if ($conn) oci_close($conn); ?>