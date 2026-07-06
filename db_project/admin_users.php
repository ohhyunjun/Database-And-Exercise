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

// 회원 데이터 초기화
$all_users = [];
$pending_users = [];
$admin_users = [];

// 전체 회원 조회 (USER_ROLE로 수정)
$sql = "SELECT U.user_id, U.username, U.USER_ROLE,
               P.name, P.age
        FROM User_Table U
        LEFT JOIN UserProfile P ON U.user_id = P.user_id
        ORDER BY U.user_id DESC";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

while($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $all_users[] = $row;
    
    // 역할별 분류
    if ($row['USER_ROLE'] == 1) {
        $pending_users[] = $row;
    } elseif ($row['USER_ROLE'] == 2) {
        $admin_users[] = $row;
    }
}

// 필터 파라미터
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 필터링된 데이터
$filtered_users = $all_users;
if ($filter == 'pending') {
    $filtered_users = $pending_users;
} elseif ($filter == 'admin') {
    $filtered_users = $admin_users;
}

// 검색 필터
if (!empty($search)) {
    $filtered_users = array_filter($filtered_users, function($user) use ($search) {
        return stripos($user['NAME'], $search) !== false || stripos($user['USERNAME'], $search) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원 관리 - 세종 청년주택</title>
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
        .table-card { 
            background: var(--white); 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            padding: 32px; 
            max-width: 1600px; 
            margin: 0 auto; 
            border: 1px solid var(--border-color); 
        }

        /* 상단 검색 영역 */
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

        /* 탭 메뉴 */
        .tabs { 
            display: flex; 
            gap: 24px; 
            border-bottom: 2px solid var(--border-color); 
            margin-bottom: 24px; 
        }
        .tab-item { 
            padding: 12px 8px; 
            font-weight: 700; 
            color: var(--text-sub); 
            cursor: pointer; 
            border-bottom: 3px solid transparent; 
            transition: 0.2s; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            font-size: 15px;
        }
        .tab-item:hover { color: var(--sejong-blue); }
        .tab-item.active { color: var(--sejong-blue); border-bottom-color: var(--sejong-blue); }
        .tab-count { 
            background: #eee; 
            padding: 3px 10px; 
            border-radius: 12px; 
            font-size: 12px; 
            margin-left: 6px; 
            font-weight: 700;
        }
        .tab-item.active .tab-count { background: var(--sejong-blue); color: white; }
        .tab-count.warning { background: var(--warning); color: white; }

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
        tr.row-pending { background-color: #FFFBF6; }
        tr.row-pending:hover { background-color: #FFF7E6; }

        /* 권한 뱃지 */
        .role-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 700; 
            border: 1px solid transparent; 
        }
        .role-admin { background: var(--sejong-light); color: var(--sejong-blue); border-color: #B3D7FF; }
        .role-pending { background: #FFF7E6; color: var(--warning); border-color: #FFD591; }
        .role-user { background: #F5F5F5; color: #666; border-color: #ddd; }

        /* 액션 버튼 */
        .btn-action { 
            padding: 6px 12px; 
            border-radius: 4px; 
            border: 1px solid transparent; 
            font-size: 12px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-right: 5px; 
            transition: 0.2s; 
        }
        .btn-approve { background: var(--success); color: white; }
        .btn-approve:hover { background: #218838; }
        .btn-revoke { background: var(--white); border-color: var(--border-color); color: #666; }
        .btn-revoke:hover { border-color: #999; color: #333; }
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
            <li><a href="admin_post_manage.php" class="menu-link">📂 공고 관리</a></li>
            <li><a href="admin_user_manage.php" class="menu-link active">👥 회원 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">👥 회원 및 권한 관리</div>
            <div class="user-profile">
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">
            <div class="table-card">
                
                <div class="top-actions">
                    <h3>👥 회원 목록 조회</h3>
                    <form method="GET" action="">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <input type="text" name="search" class="search-box" placeholder="🔍 이름/ID 검색" value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>

                <div class="tabs">
                    <a href="?filter=all" class="tab-item <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        전체 회원 <span class="tab-count"><?php echo count($all_users); ?></span>
                    </a>
                    <a href="?filter=pending" class="tab-item <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                        승인 대기 <span class="tab-count <?php echo count($pending_users) > 0 ? 'warning' : ''; ?>"><?php echo count($pending_users); ?></span>
                    </a>
                    <a href="?filter=admin" class="tab-item <?php echo $filter == 'admin' ? 'active' : ''; ?>">
                        관리자 목록 <span class="tab-count"><?php echo count($admin_users); ?></span>
                    </a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th width="140">아이디</th>
                            <th width="100">이름</th>
                            <th width="80">나이</th>
                            <th width="140">권한(Role)</th>
                            <th>관리 작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($filtered_users) > 0): ?>
                            <?php foreach($filtered_users as $user): 
                                $role_class = '';
                                $role_text = '';
                                $row_class = '';
                                
                                if ($user['USER_ROLE'] == 2) {
                                    $role_class = 'role-admin';
                                    $role_text = '👑 관리자';
                                } elseif ($user['USER_ROLE'] == 1) {
                                    $role_class = 'role-pending';
                                    $role_text = '⏳ 승인 대기';
                                    $row_class = 'row-pending';
                                } else {
                                    $role_class = 'role-user';
                                    $role_text = '👤 일반';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo $user['USER_ID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['USERNAME']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['NAME']); ?></td>
                                <td><?php echo $user['AGE']; ?>세</td>
                                <td><span class="role-badge <?php echo $role_class; ?>"><?php echo $role_text; ?></span></td>
                                <td>
                                    <?php if ($user['USER_ROLE'] == 1): ?>
                                        <button class="btn-action btn-approve" onclick="if(confirm('관리자로 승인하시겠습니까?')) location.href='admin_approve.php?id=<?php echo $user['USER_ID']; ?>'">✅ 승인</button>
                                        <button class="btn-action btn-delete" onclick="if(confirm('정말 삭제하시겠습니까?')) location.href='admin_delete_user.php?id=<?php echo $user['USER_ID']; ?>'">🗑️ 삭제</button>
                                    <?php elseif ($user['USER_ROLE'] == 2): ?>
                                        <button class="btn-action btn-revoke" onclick="if(confirm('일반 회원으로 변경하시겠습니까?')) location.href='admin_revoke.php?id=<?php echo $user['USER_ID']; ?>'">⬇ 해제</button>
                                    <?php else: ?>
                                        <button class="btn-action btn-delete" onclick="if(confirm('정말 삭제하시겠습니까?')) location.href='admin_delete_user.php?id=<?php echo $user['USER_ID']; ?>'">🗑️ 삭제</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px; color:#999;">검색된 회원이 없습니다.</td>
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