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

// 검색 및 필터 파라미터
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, active, upcoming, closed

// 기본 쿼리 작성
$sql = "SELECT 
            hp.post_id, 
            hp.post_title, 
            ht.type_name,
            TO_CHAR(hp.POST_DATE, 'YYYY-MM-DD') AS post_date, 
            TO_CHAR(hp.START_DATE, 'YYYY-MM-DD') AS start_date,
            TO_CHAR(hp.FINISH_DATE, 'YYYY-MM-DD') AS finish_date,
            hp.START_DATE,
            hp.FINISH_DATE,
            (SELECT COUNT(*) FROM HouseDetail WHERE post_id = hp.post_id) AS detail_count
        FROM HousingPost hp
        LEFT JOIN HousingType ht ON hp.type_id = ht.type_id
        WHERE 1=1";

// 검색 조건
if (!empty($search)) {
    $sql .= " AND (hp.post_title LIKE '%' || :search || '%' OR CAST(hp.post_id AS VARCHAR(10)) LIKE '%' || :search || '%')";
}

// ★ 필터 조건 수정 (접수예정 추가)
if ($filter == 'active') {
    // 진행중: 시작일 <= 오늘 <= 마감일
    $sql .= " AND SYSDATE BETWEEN hp.START_DATE AND hp.FINISH_DATE";
} elseif ($filter == 'upcoming') {
    // ★ 접수예정: 오늘 < 시작일
    $sql .= " AND SYSDATE < hp.START_DATE";
} elseif ($filter == 'closed') {
    // 종료됨: 오늘 > 마감일
    $sql .= " AND SYSDATE > hp.FINISH_DATE";
}

$sql .= " ORDER BY hp.post_id DESC";

$stmt = oci_parse($conn, $sql);

if (!empty($search)) {
    oci_bind_by_name($stmt, ':search', $search);
}

oci_execute($stmt);

$posts = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
    $posts[] = $row;
}

// ★ 통계 계산 로직 수정
$total_posts = count($posts);
$active_count = 0;
$upcoming_count = 0; // 접수예정 카운트 추가
$closed_count = 0;

foreach ($posts as $post) {
    $now = new DateTime();
    $start = new DateTime($post['START_DATE']);
    $finish = new DateTime($post['FINISH_DATE']);
    
    $now->setTime(0, 0, 0);
    $start->setTime(0, 0, 0);
    $finish->setTime(0, 0, 0);

    if ($now < $start) {
        $upcoming_count++; // 접수예정
    } elseif ($now >= $start && $now <= $finish) {
        $active_count++;   // 진행중
    } else {
        $closed_count++;   // 종료됨
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공고 관리 - 세종 청년주택</title>
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
            --warning: #FA8C16; /* 접수예정 색상 */
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            margin: 0; background-color: var(--bg-color); color: var(--text-main);
            display: flex; height: 100vh; overflow: hidden;
        }

        /* 기존 CSS 유지... */
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .brand { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; font-size: 20px; font-weight: 800; color: var(--sejong-blue); border-bottom: 1px solid var(--border-color); letter-spacing: -0.5px; }
        .brand span { color: var(--sejong-dark); margin-left: 5px; }
        .menu { list-style: none; padding: 20px 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 28px; text-decoration: none; color: var(--text-sub); font-weight: 500; transition: all 0.2s; }
        .menu-link:hover { background-color: var(--sejong-light); color: var(--sejong-blue); }
        .menu-link.active { background-color: var(--sejong-blue); color: var(--white); font-weight: 700; box-shadow: 0 2px 6px rgba(37, 108, 182, 0.3); }

        .main-layout { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-header { height: var(--header-height); background-color: var(--white); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 32px; }
        .page-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .user-profile { font-size: 14px; display: flex; gap: 10px; align-items: center; color: var(--text-sub); }
        .btn-logout { border: 1px solid var(--border-color); background: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: var(--text-sub); transition: 0.2s; }
        .btn-logout:hover { border-color: var(--sejong-blue); color: var(--sejong-blue); }

        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }
        .table-card { background: var(--white); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 32px; max-width: 1600px; margin: 0 auto; border: 1px solid var(--border-color); }

        .top-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--sejong-blue); }
        .top-actions h3 { margin: 0; color: var(--sejong-blue); font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .search-box { padding: 10px 16px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; width: 280px; transition: 0.2s; }
        .search-box:focus { outline: none; border-color: var(--sejong-blue); box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1); }

        /* 탭 메뉴 */
        .tabs { display: flex; gap: 24px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
        .tab-item { padding: 12px 8px; font-weight: 700; color: var(--text-sub); cursor: pointer; border-bottom: 3px solid transparent; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; font-size: 15px; }
        .tab-item:hover { color: var(--sejong-blue); }
        .tab-item.active { color: var(--sejong-blue); border-bottom-color: var(--sejong-blue); }
        .tab-count { background: #eee; padding: 3px 10px; border-radius: 12px; font-size: 12px; margin-left: 6px; font-weight: 700; }
        .tab-item.active .tab-count { background: var(--sejong-blue); color: white; }

        /* 테이블 */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 12px; background: #FAFBFC; border-bottom: 2px solid var(--border-color); color: var(--text-sub); font-size: 14px; font-weight: 600; }
        td { padding: 16px 12px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-main); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #F4F9FF; }

        /* 상태 뱃지 스타일 추가 */
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid transparent; }
        .badge-active { background: #D1FAE5; color: #059669; border-color: #6EE7B7; }
        .badge-upcoming { background: #FFEDD5; color: #C2410C; border-color: #FED7AA; } /* 주황색 */
        .badge-closed { background: #F3F4F6; color: #9CA3AF; border-color: #D1D5DB; }

        .btn-action { padding: 6px 12px; border-radius: 4px; border: 1px solid transparent; font-size: 12px; font-weight: 600; cursor: pointer; margin-right: 5px; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-view { background: var(--sejong-blue); color: white; }
        .btn-view:hover { background: var(--sejong-dark); }
        .btn-edit { background: var(--white); border-color: var(--sejong-blue); color: var(--sejong-blue); }
        .btn-edit:hover { background: var(--sejong-light); }
        .btn-delete { background: var(--white); border-color: var(--danger); color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand">SEJONG<span>ADMIN</span></div>
        <ul class="menu">
            <li><a href="admin_main.php" class="menu-link">📊 대시보드</a></li>
            <li><a href="admin_post_create.php" class="menu-link">📝 공고 등록</a></li>
            <li><a href="admin_post_manage.php" class="menu-link active">📂 공고 관리</a></li>
            <li><a href="admin_users.php" class="menu-link">👥 권한 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">📂 공고 관리</div>
            <div class="user-profile">
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">
            <div class="table-card">
                
                <div class="top-actions">
                    <h3>📋 공고 목록</h3>
                    <form method="GET" action="">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <input type="text" name="search" class="search-box" placeholder="🔍 공고 제목/ID 검색" value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>

                <div class="tabs">
                    <a href="?filter=all&search=<?php echo urlencode($search); ?>" class="tab-item <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        전체 공고 <span class="tab-count"><?php echo $total_posts; ?></span>
                    </a>
                    <a href="?filter=active&search=<?php echo urlencode($search); ?>" class="tab-item <?php echo $filter == 'active' ? 'active' : ''; ?>">
                        진행중 <span class="tab-count"><?php echo $active_count; ?></span>
                    </a>
                    <a href="?filter=upcoming&search=<?php echo urlencode($search); ?>" class="tab-item <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                        접수예정 <span class="tab-count"><?php echo $upcoming_count; ?></span>
                    </a>
                    <a href="?filter=closed&search=<?php echo urlencode($search); ?>" class="tab-item <?php echo $filter == 'closed' ? 'active' : ''; ?>">
                        종료됨 <span class="tab-count"><?php echo $closed_count; ?></span>
                    </a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th width="120">주택 유형</th>
                            <th>공고 제목</th>
                            <th width="100">공고일</th>
                            <th width="200">신청 기간</th>
                            <th width="80">상세</th>
                            <th width="100">상태</th>
                            <th width="180">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($posts) > 0): ?>
                            <?php foreach($posts as $post): 
                                // 날짜 비교 로직
                                $now = new DateTime();
                                $start = new DateTime($post['START_DATE']);
                                $finish = new DateTime($post['FINISH_DATE']);
                                $now->setTime(0,0,0); $start->setTime(0,0,0); $finish->setTime(0,0,0);
                                
                                $status_html = '';
                                if ($now < $start) {
                                    $status_html = '<span class="badge badge-upcoming">접수예정</span>';
                                } elseif ($now >= $start && $now <= $finish) {
                                    $status_html = '<span class="badge badge-active">진행중</span>';
                                } else {
                                    $status_html = '<span class="badge badge-closed">종료</span>';
                                }
                            ?>
                            <tr>
                                <td><strong>#<?php echo $post['POST_ID']; ?></strong></td>
                                <td><?php echo htmlspecialchars($post['TYPE_NAME']); ?></td>
                                <td><?php echo htmlspecialchars($post['POST_TITLE']); ?></td>
                                <td><?php echo $post['POST_DATE']; ?></td>
                                <td><?php echo $post['START_DATE']; ?> ~ <?php echo $post['FINISH_DATE']; ?></td>
                                <td><?php echo $post['DETAIL_COUNT']; ?>개</td>
                                <td><?php echo $status_html; ?></td>
                                <td>
                                    <a href="post_detail.php?id=<?php echo $post['POST_ID']; ?>" class="btn-action btn-view">👁️ 보기</a>
                                    <a href="admin_post_edit.php?id=<?php echo $post['POST_ID']; ?>" class="btn-action btn-edit">✏️ 수정</a>
                                    <button class="btn-action btn-delete" onclick="if(confirm('정말 삭제하시겠습니까?')) location.href='admin_post_delete.php?id=<?php echo $post['POST_ID']; ?>'">🗑️ 삭제</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:#999;">검색된 공고가 없습니다.</td>
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