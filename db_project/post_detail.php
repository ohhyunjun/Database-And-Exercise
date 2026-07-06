<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db_conn.php'; 

if (!isset($_GET['id'])) {
    die("ID 없음");
}

$post_id = $_GET['id'];

if (!$conn) die("DB 연결 실패");

// 1. 공고 기본 정보 (건드리지 않음)
$sql = "SELECT P.*, T.type_name 
        FROM HousingPost P 
        LEFT JOIN HousingType T ON P.type_id = T.type_id 
        WHERE P.post_id = :pid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':pid', $post_id);
oci_execute($stmt);
$post = oci_fetch_array($stmt, OCI_ASSOC);

// 2. 자격요건 (건드리지 않음)
$sql = "SELECT * FROM EligibilityCriteria WHERE post_id = :pid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':pid', $post_id);
oci_execute($stmt);
$criteria = [];
while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $criteria[] = $row;
}

// 3. 주택상세 (건드리지 않음)
$sql = "SELECT D.*, R.dong_name 
        FROM HouseDetail D 
        LEFT JOIN Region R ON D.region_id = R.region_id 
        WHERE D.post_id = :pid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':pid', $post_id);
oci_execute($stmt);
$details = [];
while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $details[] = $row;
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['POST_TITLE']); ?> - 상세정보</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* === [공통 디자인 변수] === */
        :root {
            --primary: #256CB6;      
            --primary-dark: #1a4a80; 
            --accent: #00C73C;       
            --bg-light: #F8F9FA;     
            --text-main: #333;       
            --text-sub: #666;        
            --white: #fff;
            --border: #e1e1e1;
            --danger: #FF4D4F;       
        }

        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; color: var(--text-main); background-color: var(--bg-light); display: flex; flex-direction: column; min-height: 100vh; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        ul { list-style: none; padding: 0; margin: 0; }

        /* === [상단 내비게이션 바] === */
        .navbar {
            background: var(--white); height: 70px; 
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 22px; font-weight: 900; color: var(--primary); display: flex; align-items: center; gap: 6px; z-index: 2; }
        .logo span { color: var(--text-main); font-weight: 400; font-size: 18px; }
        
        .nav-menu { display: flex; gap: 35px; position: absolute; left: 50%; transform: translateX(-50%); }
        .nav-link { font-weight: 600; font-size: 16px; color: #444; padding: 10px 0; }
        .nav-link:hover { color: var(--primary); }
        
        .nav-auth { display: flex; gap: 10px; z-index: 2; align-items: center; }
        .btn-login { 
            border: 1px solid var(--primary); color: var(--primary); background: white;
            padding: 8px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-login:hover { background: var(--primary); color: white; }
        .user-name { font-weight: 700; color: var(--text-main); font-size: 14px; }

        /* === [상세 페이지 컨텐츠 디자인 - 넓게 수정됨] === */
        /* ★ 수정 포인트: max-width를 1200px로 늘림 */
        .container { max-width: 1200px; width: 95%; margin: 40px auto; flex: 1; padding: 0 20px; }

        /* 헤더 섹션 */
        .post-header {
            background: var(--white); border-radius: 12px; border: 1px solid var(--border);
            padding: 40px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .type-badge {
            display: inline-block; background: #E6F2FF; color: var(--primary); 
            padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 14px; margin-bottom: 15px;
        }
        .post-title { font-size: 32px; font-weight: 800; color: #111; margin: 0 0 25px 0; line-height: 1.4; }
        
        /* 정보 테이블 스타일 */
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th { 
            text-align: left; width: 160px; color: var(--text-sub); 
            padding: 15px 0; border-bottom: 1px solid #f0f0f0; font-weight: 500; font-size: 15px;
        }
        .info-table td { 
            padding: 15px 0; border-bottom: 1px solid #f0f0f0; 
            font-weight: 600; color: var(--text-main); font-size: 16px;
        }
        .info-table tr:last-child th, .info-table tr:last-child td { border-bottom: none; }

        /* 섹션 타이틀 */
        .section-title { 
            font-size: 24px; font-weight: 800; color: var(--text-main); margin: 60px 0 20px 0; 
            display: flex; align-items: center; gap: 10px; border-left: 6px solid var(--primary); padding-left: 15px;
        }

        /* 카드 박스 스타일 */
        .content-card {
            background: var(--white); border: 1px solid var(--border); border-radius: 12px;
            padding: 35px; margin-bottom: 25px; transition: transform 0.2s;
        }
        .content-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(37, 108, 182, 0.1); }
        
        .sub-title { font-size: 20px; font-weight: 700; color: #222; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        
        /* 가격 강조 */
        .price-text { color: var(--danger); font-weight: 800; font-size: 18px; }

        /* 버튼 영역 */
        .btn-area { text-align: center; margin-top: 80px; }
        .btn-back {
            display: inline-block; background: #6c757d; color: white; 
            padding: 16px 60px; border-radius: 40px; font-size: 17px; font-weight: 700; 
            transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-back:hover { background: #5a6268; transform: translateY(-2px); }

        /* === [푸터] === */
        .footer { background: #2c3e50; color: #bdc3c7; padding: 40px 0; margin-top: auto; text-align: center; font-size: 13px; line-height: 1.6; }
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

    <div class="container">
        
        <div class="post-header">
            <span class="type-badge"><?php echo htmlspecialchars($post['TYPE_NAME']); ?></span>
            <h1 class="post-title"><?php echo htmlspecialchars($post['POST_TITLE']); ?></h1>

            <table class="info-table">
                <tr>
                    <th>공고 번호</th>
                    <td><?php echo $post['POST_ID']; ?></td>
                </tr>
                <tr>
                    <th>게시일</th>
                    <td><?php echo date('Y-m-d', strtotime($post['POST_DATE'])); ?></td>
                </tr>
                <tr>
                    <th>접수 기간</th>
                    <td>
                        <?php echo date('Y-m-d', strtotime($post['START_DATE'])); ?> ~ 
                        <?php echo date('Y-m-d', strtotime($post['FINISH_DATE'])); ?>
                    </td>
                </tr>
                <tr>
                    <th>공고 링크</th>
                    <td>
                        <?php 
                        // CLOB 데이터 처리: 객체인 경우 load() 호출, 문자열인 경우 그대로 출력
                        $urlVal = $post['POST_URL'];
                        if (is_object($urlVal)) { 
                            $urlVal = $urlVal->load(); 
                        }
                        
                        if(!empty($urlVal)): ?>
                            <a href="<?php echo htmlspecialchars($urlVal); ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-weight: 700;">
                                원문 바로가기 🔗
                            </a>
                        <?php else: ?>
                            <span style="color:#999;">링크 없음</span>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
        </div>

        <div class="section-title">✅ 신청 자격 요건</div>
        <?php foreach($criteria as $cr): ?>
        <div class="content-card">
            <div class="sub-title">🔹 <?php echo htmlspecialchars($cr['CRITERIA_NAME']); ?></div>
            <table class="info-table">
                <tr>
                    <th>세종시 거주</th>
                    <td><?php echo $cr['IS_SEJONG_RESIDENT'] == 1 ? '필수' : '무관'; ?></td>
                </tr>
                <tr>
                    <th>결혼 여부</th>
                    <td><?php echo $cr['IS_MARRIED'] == 1 ? '기혼자만' : '무관'; ?></td>
                </tr>
                <tr>
                    <th>주택 소유</th>
                    <td><?php echo $cr['IS_HOME'] == 0 ? '무주택자만' : '유주택자 가능'; ?></td>
                </tr>
                <tr>
                    <th>연령 제한</th>
                    <td>
                        <?php 
                        $age_min = $cr['AGE_MIN'] ?? '-';
                        $age_max = $cr['AGE_MAX'] ?? '-';
                        if($age_min != '-' && $age_max != '-') {
                            echo "{$age_min}세 ~ {$age_max}세";
                        } elseif($age_min != '-') {
                            echo "{$age_min}세 이상";
                        } elseif($age_max != '-') {
                            echo "{$age_max}세 이하";
                        } else {
                            echo '제한 없음';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>자산 한도</th>
                    <td><?php echo isset($cr['ASSET_LIMIT']) && $cr['ASSET_LIMIT'] !== null ? number_format($cr['ASSET_LIMIT']) . '원 이하' : '-'; ?></td>
                </tr>
                <tr>
                    <th>차량가액 한도</th>
                    <td><?php echo isset($cr['CAR_LIMIT']) && $cr['CAR_LIMIT'] !== null ? number_format($cr['CAR_LIMIT']) . '원 이하' : '-'; ?></td>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>

        <div class="section-title">🏠 공급 주택 상세 정보</div>
        <?php foreach($details as $dt): ?>
        <div class="content-card">
            <div class="sub-title"><?php echo htmlspecialchars($dt['COMPLEX_NAME']); ?></div>
            <table class="info-table">
                <tr>
                    <th>위치 (동)</th>
                    <td><?php echo htmlspecialchars($dt['DONG_NAME']); ?></td>
                </tr>
                <tr>
                    <th>주택 유형</th>
                    <td><?php echo htmlspecialchars($dt['HOUSE_TYPE_NAME']); ?></td>
                </tr>
                <tr>
                    <th>전용면적</th>
                    <td><?php echo $dt['AREA']; ?>㎡</td>
                </tr>
                <tr>
                    <th>보증금</th>
                    <td class="price-text"><?php echo number_format($dt['DEPOSIT']); ?>원</td>
                </tr>
                <tr>
                    <th>월 임대료</th>
                    <td class="price-text"><?php echo number_format($dt['MONTHLY_RENT']); ?>원</td>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>

        <div class="btn-area">
            <?php 
            // 관리자(role == 2)라면 관리자 목록으로, 아니면 일반 공고 목록으로 이동
            $backLink = (isset($_SESSION['role']) && $_SESSION['role'] == 2) ? "admin_post_manage.php" : "post_list.php"; 
            ?>
            <a href="<?php echo $backLink; ?>" class="btn-back">목록으로 돌아가기</a>
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