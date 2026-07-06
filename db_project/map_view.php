<?php
session_start();
include 'db_conn.php';

$posts = [];
if ($conn) {
    $sql = "SELECT P.post_id, P.post_title, T.type_name, D.complex_name, D.house_type_name,
               D.deposit, D.monthly_rent, D.AREA, R.dong_name, R.region_id,
               TO_CHAR(P.FINISH_DATE, 'YYYY.MM.DD') AS finish_date,
               CEIL(P.FINISH_DATE - SYSDATE) AS d_day
            FROM HousingPost P
            JOIN HousingType T ON P.type_id = T.type_id
            JOIN HouseDetail D ON P.post_id = D.post_id
            LEFT JOIN Region R ON D.region_id = R.region_id
            WHERE P.FINISH_DATE >= SYSDATE
            ORDER BY P.post_id DESC";
    
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    
    while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $posts[] = $row;
    }
    
    oci_close($conn);
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>지도로 보기 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #256CB6;
            --primary-dark: #1a4a80;
            --bg-light: #F8F9FA;
            --white: #fff;
            --border: #e1e1e1;
            --text-main: #333;
            --text-sub: #666;
            --danger: #FF4D4F;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Noto Sans KR', sans-serif; color: var(--text-main); overflow: hidden; }

        .navbar {
            height: 70px; background: var(--white); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 6px; text-decoration: none; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }
        .nav-menu { display: flex; gap: 35px; list-style: none; }
        .nav-link { font-weight: 600; font-size: 16px; color: #444; padding: 10px 0; text-decoration: none; transition: 0.2s; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active { color: var(--primary); }
        .nav-auth { display: flex; gap: 10px; align-items: center; }
        .btn-login { border: 1px solid var(--primary); color: var(--primary); background: white; padding: 8px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-login:hover { background: var(--primary); color: white; }
        .user-name { font-weight: 700; color: var(--text-main); font-size: 14px; }

        .container { display: flex; height: calc(100vh - 70px); }
        .map-area { flex: 7; position: relative; }
        #map { width: 100%; height: 100%; }
        .list-area { flex: 3; background: var(--bg-light); overflow-y: auto; border-left: 1px solid var(--border); }
        .list-header { padding: 20px; background: var(--white); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10; }
        .list-header h2 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .list-count { font-size: 14px; color: var(--text-sub); }
        .list-count strong { color: var(--primary); font-weight: 700; }

        .post-list { padding: 15px; }
        .post-card {
            background: var(--white); border-radius: 12px; padding: 20px; margin-bottom: 15px;
            cursor: pointer; transition: all 0.3s; border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: relative;
        }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(37,108,182,0.15); border-color: var(--primary); }
        .post-card.active { border-color: var(--primary); background: #f0f7ff; }
        .card-badge { display: inline-block; padding: 4px 10px; background: var(--primary); color: white; font-size: 11px; font-weight: 600; border-radius: 4px; margin-bottom: 8px; }
        .card-title { font-size: 15px; font-weight: 700; margin-bottom: 10px; line-height: 1.4; }
        .card-info { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .card-info-item { font-size: 13px; color: var(--text-sub); }
        .card-info-item strong { color: var(--text-main); font-weight: 600; margin-left: 4px; }
        .card-price { display: flex; gap: 10px; padding-top: 12px; border-top: 1px solid var(--border); margin-bottom: 12px; }
        .price-item { flex: 1; font-size: 12px; color: var(--text-sub); }
        .price-value { display: block; font-size: 16px; font-weight: 700; color: var(--primary); margin-top: 3px; }
        .card-dday { position: absolute; top: 15px; right: 15px; background: var(--danger); color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .card-actions { display: flex; gap: 8px; }
        .btn-detail { flex: 1; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; text-align: center; text-decoration: none; display: block; }
        .btn-detail:hover { background: var(--primary-dark); }
        .no-data { text-align: center; padding: 60px 20px; color: var(--text-sub); }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="main.php" class="logo">🏡 세종<span>청년주택</span></a>
        <ul class="nav-menu">
            <li><a href="post_list.php" class="nav-link">공고목록</a></li>
            <li><a href="map_view.php" class="nav-link active">지도보기</a></li>
            <li><a href="mypage.php" class="nav-link">마이페이지</a></li>
        </ul>
        <div class="nav-auth">
            <?php if(isset($_SESSION['username'])): ?>
                <span class="user-name"><?php echo $_SESSION['username']; ?>님</span>
                <a href="logout.php" class="btn-login">로그아웃</a>
            <?php else: ?>
                <a href="login.php" class="btn-login">로그인</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="map-area">
            <div id="map"></div>
        </div>

        <div class="list-area">
            <div class="list-header">
                <h2>📋 진행 중인 공고</h2>
                <p class="list-count">총 <strong><?php echo count($posts); ?></strong>개의 공고가 있습니다</p>
            </div>

            <div class="post-list">
                <?php if(count($posts) > 0): ?>
                    <?php foreach($posts as $index => $post): ?>
                    <div class="post-card" 
                         data-index="<?php echo $index; ?>" 
                         data-post-id="<?php echo $post['POST_ID']; ?>"
                         data-lat="" 
                         data-lng="" 
                         data-complex="<?php echo htmlspecialchars($post['COMPLEX_NAME']); ?>" 
                         data-dong="<?php echo htmlspecialchars($post['DONG_NAME']); ?>"
                         data-region-id="<?php echo $post['REGION_ID']; ?>">
                        
                        <span class="card-badge"><?php echo htmlspecialchars($post['TYPE_NAME']); ?></span>
                        
                        <?php if($post['D_DAY'] <= 7): ?>
                        <span class="card-dday">D-<?php echo $post['D_DAY']; ?></span>
                        <?php endif; ?>
                        
                        <div class="card-title">
                            <?php echo htmlspecialchars($post['POST_TITLE']); ?>
                        </div>

                        <div class="card-info">
                            <div class="card-info-item">
                                📍 <strong><?php echo htmlspecialchars($post['DONG_NAME']); ?></strong>
                            </div>
                            <div class="card-info-item">
                                🏢 <?php echo htmlspecialchars($post['COMPLEX_NAME']); ?>
                            </div>
                            <div class="card-info-item">
                                📐 <?php echo htmlspecialchars($post['HOUSE_TYPE_NAME']); ?> 
                                (<?php echo number_format($post['AREA'], 2); ?>㎡)
                            </div>
                        </div>

                        <div class="card-price">
                            <div class="price-item">
                                보증금
                                <span class="price-value"><?php echo number_format($post['DEPOSIT']); ?>원</span>
                            </div>
                            <div class="price-item">
                                월세
                                <span class="price-value"><?php echo number_format($post['MONTHLY_RENT']); ?>원</span>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="post_detail.php?id=<?php echo $post['POST_ID']; ?>" class="btn-detail">
                                📄 상세보기
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        📭 현재 진행 중인 공고가 없습니다.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=YOUR_KAKAO_API_KEY&..."></script>
    <script>
        kakao.maps.load(function() {
            const posts = <?php echo json_encode($posts, JSON_UNESCAPED_UNICODE); ?>;
            
            console.log('📍 총 공고 수:', posts.length);
            
            const mapContainer = document.getElementById('map');
            const mapOption = {
                center: new kakao.maps.LatLng(36.4800, 127.2890),
                level: 6
            };
            const map = new kakao.maps.Map(mapContainer, mapOption);
            
            if (posts.length === 0) {
                const message = new kakao.maps.CustomOverlay({
                    position: new kakao.maps.LatLng(36.4800, 127.2890),
                    content: `<div style="padding:20px;background:white;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);text-align:center;">
                                <div style="font-size:32px;margin-bottom:10px;">🗺️</div>
                                <div style="font-size:16px;font-weight:700;color:#256CB6;margin-bottom:5px;">세종특별자치시</div>
                                <div style="font-size:13px;color:#666;">현재 진행 중인 공고가 없습니다</div>
                             </div>`,
                    yAnchor: 1
                });
                message.setMap(map);
                return;
            }
            
            const places = new kakao.maps.services.Places();
            const markers = [];
            const infowindows = [];
            
            // Fallback 좌표
            const regionCoords = {
                '1': { lat: 36.5042, lng: 127.2611, name: '새뜸마을' },
                '2': { lat: 36.5012, lng: 127.2897, name: '해들마을' },
                '3': { lat: 36.4892, lng: 127.2589, name: '가온마을' }
            };
            
            posts.forEach((post, index) => {
                console.log(`\n🏠 공고 ${index + 1}:`, post.POST_TITLE);
                console.log('  - 단지:', post.COMPLEX_NAME);
                
                const keyword = `세종 ${post.COMPLEX_NAME}`;
                console.log('  - 검색 키워드:', keyword);
                
                places.keywordSearch(keyword, function(result, status) {
                    let coords;
                    const card = document.querySelector(`.post-card[data-index="${index}"]`);
                    
                    if (status === kakao.maps.services.Status.OK && result.length > 0) {
                        console.log('  ✅ 검색 성공:', result[0].place_name);
                        coords = new kakao.maps.LatLng(result[0].y, result[0].x);
                        
                        if (card) {
                            card.dataset.lat = result[0].y;
                            card.dataset.lng = result[0].x;
                        }
                    } else {
                        console.log('  ⚠️ 검색 실패 → fallback');
                        const regionId = post.REGION_ID || '1';
                        const fallback = regionCoords[regionId] || regionCoords['1'];
                        coords = new kakao.maps.LatLng(fallback.lat, fallback.lng);
                        
                        if (card) {
                            card.dataset.lat = fallback.lat;
                            card.dataset.lng = fallback.lng;
                        }
                    }
                    
                    const marker = new kakao.maps.Marker({
                        map: map,
                        position: coords,
                        title: post.COMPLEX_NAME
                    });
                    
                    const infowindow = new kakao.maps.InfoWindow({
                        content: `<div style="padding:12px;font-size:12px;width:220px;line-height:1.5;">
                                    <strong style="color:#256CB6;font-size:13px;">${post.POST_TITLE}</strong><br/>
                                    📍 ${post.DONG_NAME} ${post.COMPLEX_NAME}<br/>
                                    💰 보증금 ${Number(post.DEPOSIT).toLocaleString()}원 / 월세 ${Number(post.MONTHLY_RENT).toLocaleString()}원<br/>
                                    <a href="post_detail.php?id=${post.POST_ID}" style="color:#256CB6;font-weight:600;text-decoration:underline;margin-top:5px;display:inline-block;">상세보기 →</a>
                                  </div>`
                    });
                    
                    markers[index] = marker;
                    infowindows[index] = infowindow;
                    
                    kakao.maps.event.addListener(marker, 'click', function() {
                        infowindows.forEach(iw => iw.close());
                        infowindow.open(map, marker);
                        
                        document.querySelectorAll('.post-card').forEach(c => c.classList.remove('active'));
                        if (card) {
                            card.classList.add('active');
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                });
            });
            
            document.querySelectorAll('.post-card').forEach((card, index) => {
                card.addEventListener('click', function(e) {
                    if (e.target.classList.contains('btn-detail') || e.target.closest('.btn-detail')) {
                        return;
                    }
                    
                    const lat = parseFloat(this.dataset.lat);
                    const lng = parseFloat(this.dataset.lng);
                    
                    if (lat && lng) {
                        const moveLatLon = new kakao.maps.LatLng(lat, lng);
                        map.setCenter(moveLatLon);
                        map.setLevel(3);
                        
                        infowindows.forEach(iw => iw && iw.close());
                        
                        if (infowindows[index]) {
                            infowindows[index].open(map, markers[index]);
                        }
                        
                        if (markers[index]) {
                            markers[index].setAnimation(kakao.maps.Marker.AnimationType.BOUNCE);
                            setTimeout(() => markers[index].setAnimation(null), 600);
                        }
                        
                        document.querySelectorAll('.post-card').forEach(c => c.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });
        });
    </script>

</body>
</html>