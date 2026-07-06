<?php
session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: login.php");
    exit();
}

// DB 연결
include 'db_conn.php';

if (!$conn) {
    die("<script>alert('DB 연결 실패'); location.href='admin_main.php';</script>");
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : '관리자';

// 검색 파라미터
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 신청 현황 조회 (User_House 테이블 기반)
$sql = "SELECT 
            uh.user_id,
            uh.post_id,
            u.username,
            up.name,
            up.age,
            hp.post_title,
            ht.type_name,
            TO_CHAR(hp.POST_DATE, 'YYYY-MM-DD') AS post_date
        FROM User_House uh
        INNER JOIN User_Table u ON uh.user_id = u.user_id
        LEFT JOIN UserProfile up ON uh.user_id = up.user_id
        INNER JOIN HousingPost hp ON uh.post_id = hp.post_id
        LEFT JOIN HousingType ht ON hp.type_id = ht.type_id
        WHERE 1=1";

// 검색 조건
if (!empty($search)) {
    $sql .= " AND (up.name LIKE '%' || :search || '%' 
              OR u.username LIKE '%' || :search || '%' 
              OR hp.post_title LIKE '%' || :search || '%'
              OR CAST(uh.user_id AS VARCHAR(10)) LIKE '%' || :search || '%'
              OR CAST(uh.post_id AS VARCHAR(10)) LIKE '%' || :search || '%')";
}

$sql .= " ORDER BY uh.post_id DESC, uh.user_id DESC";

$stmt = oci_parse($conn, $sql);

if (!empty($search)) {
    oci_bind_by_name($stmt, ':search', $search);
}

oci_execute($stmt);

$applications = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
    $applications[] = $row;
}

// 통계
$total_applications = count($applications);

// 공고별 신청자 수 집계
$post_stats = [];
foreach ($applications as $app) {
    $post_id = $app['POST_ID'];
    if (!isset($post_stats[$post_id])) {
        $post_stats[$post_id] = [
            'post_title' => $app['POST_TITLE'],
            'type_name' => $app['TYPE_NAME'],
            'count' => 0
        ];
    }
    $post_stats[$post_id]['count']++;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공고 신청 현황 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sejong-blue: #256CB6;
            --sejong-dark: #1a4a80;
            --sejong-light: #E6F2FF;
            --bg-color: #F5F7FA;
            --white: #FFFFFF;
            --border-color: #DDE2E5;
            --text-main: #333333;
            --text-sub: #666666;
            --danger: #FF4D4F;
            --success: #28a745;
            --warning: #FA8C16;
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            margin: 0; background-color: var(--bg-color); color: var(--text-main);
            display: flex; height: 100vh; overflow: hidden;
        }

        /* 사이드바 */
        .sidebar { 
            width: var(--sidebar-width); 
            background-color: var(--white); 
            border-right: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
            z-index: 100; 
        }
        .brand { 
            height: var(--header-height); 
            display: flex; 
            align-items: center; 
            padding: 0 24px; 
            font-size: 20px; 
            font-weight: 800; 
            color: var(--sejong-blue); 
            border-bottom: 1px solid var(--border-color); 
            letter-spacing: -0.5px; 
        }
        .brand span { color: var(--sejong-dark); margin-left: 5px; }
        .menu { list-style: none; padding: 20px 0; margin: 0; }
        .menu-link { 
            display: flex; 
            align-items: center; 
            padding: 12px 28px; 
            text-decoration: none; 
            color: var(--text-sub); 
            font-weight: 500; 
            transition: all 0.2s; 
        }
        .menu-link:hover { background-color: var(--sejong-light); color: var(--sejong-blue); }
        .menu-link.active { 
            background-color: var(--sejong-blue); 
            color: var(--white); 
            font-weight: 700; 
            box-shadow: 0 2px 6px rgba(37, 108, 182, 0.3); 
        }

        /* 메인 레이아웃 */
        .main-layout { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-header { 
            height: var(--header-height); 
            background-color: var(--white); 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0 32px; 
        }
        .page-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .user-profile { 
            font-size: 14px; 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            color: var(--text-sub); 
        }
        .btn-logout { 
            border: 1px solid var(--border-color); 
            background: white; 
            padding: 6px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px; 
            color: var(--text-sub); 
            transition: 0.2s; 
        }
        .btn-logout:hover { border-color: var(--sejong-blue); color: var(--sejong-blue); }

        /* 컨텐츠 */
        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }
        
        /* 통계 카드 */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            max-width: 1600px;
            margin: 0 auto 32px;
        }
        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(37, 108, 182, 0.15);
            border-color: var(--sejong-blue);
        }
        .stat-title {
            font-size: 14px;
            color: var(--text-sub);
            font-weight: 600;
            margin-bottom: 12px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--sejong-blue);
        }
        .stat-icon {
            font-size: 24px;
            float: right;
            opacity: 0.3;
        }

        .table-card { 
            background: var(--white); 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            padding: 32px; 
            max-width: 1600px; 
            margin: 0 auto 32px; 
            border: 1px solid var(--border-color); 
        }

        /* 상단 영역 */
        .top-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            padding-bottom: 16px; 
            border-bottom: 2px solid var(--sejong-blue);
        }
        .top-actions h3 { 
            margin: 0; 
            color: var(--sejong-blue); 
            font-size: 20px; 
            font-weight: 700;
            display: flex; 
            align-items: center; 
            gap: 8px;
        }
        .search-box { 
            padding: 10px 16px; 
            border: 1px solid var(--border-color); 
            border-radius: 6px; 
            font-size: 14px; 
            width: 280px; 
            transition: 0.2s;
        }
        .search-box:focus { 
            outline: none; 
            border-color: var(--sejong-blue); 
            box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1);
        }

        /* 테이블 */
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; 
            padding: 16px 12px; 
            background: #FAFBFC; 
            border-bottom: 2px solid var(--border-color); 
            color: var(--text-sub); 
            font-size: 14px; 
            font-weight: 600;
        }
        td { 
            padding: 16px 12px; 
            border-bottom: 1px solid var(--border-color); 
            font-size: 14px; 
            color: var(--text-main); 
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #F4F9FF; }

        /* 뱃지 */
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            background: var(--sejong-light);
            color: var(--sejong-blue);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand">SEJONG<span>ADMIN</span></div>
        <ul class="menu">
            <li><a href="admin_main.php" class="menu-link">📊 대시보드</a></li>
            <li><a href="admin_post_create.php" class="menu-link">📝 공고 등록</a></li>
            <li><a href="admin_post_manage.php" class="menu-link">📂 공고 관리</a></li>
            <li><a href="admin_users.php" class="menu-link">👥 권한 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">📊 공고 신청 현황</div>
            <div class="user-profile">
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">

            <!-- 통계 카드 -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-title">총 신청 건수</div>
                    <div class="stat-value"><?php echo number_format($total_applications); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏠</div>
                    <div class="stat-title">신청된 공고 수</div>
                    <div class="stat-value"><?php echo count($post_stats); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-title">신청 회원 수</div>
                    <div class="stat-value"><?php echo count(array_unique(array_column($applications, 'USER_ID'))); ?></div>
                </div>
            </div>

            <!-- 공고별 신청 통계 -->
            <?php if (count($post_stats) > 0): ?>
            <div class="table-card">
                <div class="section-title">📊 공고별 신청 현황</div>
                <table>
                    <thead>
                        <tr>
                            <th width="80">공고 ID</th>
                            <th width="120">주택 유형</th>
                            <th>공고 제목</th>
                            <th width="100">신청자 수</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($post_stats as $post_id => $stat): ?>
                        <tr>
                            <td><strong>#<?php echo $post_id; ?></strong></td>
                            <td><span class="type-badge"><?php echo htmlspecialchars($stat['type_name']); ?></span></td>
                            <td><?php echo htmlspecialchars($stat['post_title']); ?></td>
                            <td><strong><?php echo number_format($stat['count']); ?>명</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- 전체 신청 목록 -->
            <div class="table-card">
                
                <div class="top-actions">
                    <h3>📝 전체 신청 목록</h3>
                    <form method="GET" action="">
                        <input type="text" name="search" class="search-box" placeholder="🔍 이름/아이디/공고 검색" value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th width="80">회원 ID</th>
                            <th width="120">아이디</th>
                            <th width="100">이름</th>
                            <th width="60">나이</th>
                            <th width="80">공고 ID</th>
                            <th width="120">주택 유형</th>
                            <th>공고 제목</th>
                            <th width="100">공고일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($applications) > 0): ?>
                            <?php foreach($applications as $app): ?>
                            <tr>
                                <td><?php echo $app['USER_ID']; ?></td>
                                <td><?php echo htmlspecialchars($app['USERNAME']); ?></td>
                                <td><strong><?php echo htmlspecialchars($app['NAME']); ?></strong></td>
                                <td><?php echo $app['AGE']; ?>세</td>
                                <td><strong>#<?php echo $app['POST_ID']; ?></strong></td>
                                <td><span class="type-badge"><?php echo htmlspecialchars($app['TYPE_NAME']); ?></span></td>
                                <td><?php echo htmlspecialchars($app['POST_TITLE']); ?></td>
                                <td><?php echo $app['POST_DATE']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:#999;">신청 내역이 없습니다.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>

</body>
</html>

<?php
if ($conn) @oci_close($conn);
?>