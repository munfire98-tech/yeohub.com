<?php
/* =============================================================
   blog.php — 현장의 기록 (커뮤니티 블로그)
   ─────────────────────────────────────────────────────────────
   로그인한 사용자가 자유롭게 글을 쓰고, 읽고, 댓글을 남깁니다.
   글은 누구나 읽을 수 있고, 쓰기·댓글·수정·삭제는 로그인이 필요합니다.
   자기 글/댓글만 수정·삭제할 수 있고, 관리자는 전체를 관리합니다.

   저장 위치: data/blog/posts.json (전체 공용 — 회원별 폴더가 아닙니다)
   ============================================================= */
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ── 회원 식별 (있으면 user_key.php 를 그대로 활용) ── */
if (is_file(__DIR__ . '/user_key.php')) require_once __DIR__ . '/user_key.php';

function blog_uid(): string {
  if (function_exists('app_user_key')) { $k = app_user_key(); if ($k !== '') return $k; }
  if (!empty($_SESSION['member_id'])) return (string)$_SESSION['member_id'];
  if (!empty($_SESSION['kakao_id']))  return 'kakao_' . $_SESSION['kakao_id'];
  return '';
}
function blog_uname(): string {
  if (!empty($_SESSION['nickname'])) return (string)$_SESSION['nickname'];
  $uid = blog_uid();
  return $uid !== '' ? $uid : '방문자';
}
function blog_is_admin(): bool {
  return (!empty($_SESSION['is_admin'])) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function blog_logged_in(): bool { return blog_uid() !== '' || blog_is_admin(); }
function blog_can_edit(array $ownerUid): bool {
  $uid = blog_uid();
  return blog_is_admin() || ($uid !== '' && in_array($uid, $ownerUid, true));
}

/* ── 저장소 ── */
const BLOG_CATS = ['일반', '현장 꿀팁', '질문', '후기'];
function blog_file(): string {
  $dir = __DIR__ . '/data/blog';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/posts.json';
}
function blog_read(): array {
  $f = blog_file();
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function blog_write(array $posts): bool {
  $f = blog_file();
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
function blog_uuid(): string {
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── POST 처리 (헤더 include 전에 끝내야 리다이렉트가 됩니다) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrfOk = hash_equals($CSRF, (string)($_POST['csrf'] ?? ''));

  if ($csrfOk && $action === 'create' && blog_logged_in()) {
    $title = trim((string)($_POST['title'] ?? ''));
    $cat   = (string)($_POST['cat'] ?? '일반');
    $body  = trim((string)($_POST['body'] ?? ''));
    if (!in_array($cat, BLOG_CATS, true)) $cat = '일반';
    if ($cat === '공지' && !blog_is_admin()) $cat = '일반';
    if ($title !== '' && $body !== '') {
      $posts = blog_read();
      $now = date('Y-m-d H:i:s');
      $posts[] = [
        'id' => blog_uuid(), 'title' => mb_substr($title, 0, 120), 'cat' => $cat, 'body' => $body,
        'pinned' => (blog_is_admin() && !empty($_POST['pinned'])),
        'author_uid' => blog_uid(), 'author_name' => blog_uname(),
        'created_at' => $now, 'updated_at' => $now, 'comments' => [],
      ];
      blog_write($posts);
      header('Location: blog.php?view=post&id=' . urlencode(end($posts)['id'])); exit;
    }
    header('Location: blog.php?view=write&err=empty'); exit;
  }

  if ($csrfOk && $action === 'update' && blog_logged_in()) {
    $id = (string)($_POST['id'] ?? '');
    $posts = blog_read();
    foreach ($posts as &$p) {
      if (($p['id'] ?? '') === $id && blog_can_edit([$p['author_uid'] ?? '__none__'])) {
        $title = trim((string)($_POST['title'] ?? ''));
        $cat   = (string)($_POST['cat'] ?? $p['cat']);
        $body  = trim((string)($_POST['body'] ?? ''));
        if (!in_array($cat, BLOG_CATS, true)) $cat = $p['cat'];
        if ($cat === '공지' && !blog_is_admin()) $cat = '일반';
        if ($title !== '') $p['title'] = mb_substr($title, 0, 120);
        if ($body  !== '') $p['body']  = $body;
        $p['cat'] = $cat;
        if (blog_is_admin()) $p['pinned'] = !empty($_POST['pinned']);
        $p['updated_at'] = date('Y-m-d H:i:s');
        break;
      }
    }
    unset($p);
    blog_write($posts);
    header('Location: blog.php?view=post&id=' . urlencode($id)); exit;
  }

  if ($csrfOk && $action === 'delete' && blog_logged_in()) {
    $id = (string)($_POST['id'] ?? '');
    $posts = blog_read();
    $posts = array_values(array_filter($posts, function ($p) use ($id) {
      if (($p['id'] ?? '') !== $id) return true;
      return !blog_can_edit([$p['author_uid'] ?? '__none__']);   // 권한 없으면 안 지워짐(=true 남김)
    }));
    blog_write($posts);
    header('Location: blog.php'); exit;
  }

  if ($csrfOk && $action === 'comment_add' && blog_logged_in()) {
    $id   = (string)($_POST['id'] ?? '');
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text !== '') {
      $posts = blog_read();
      foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $id) {
          $p['comments'][] = [
            'id' => blog_uuid(), 'text' => mb_substr($text, 0, 500),
            'author_uid' => blog_uid(), 'author_name' => blog_uname(),
            'created_at' => date('Y-m-d H:i:s'),
          ];
          break;
        }
      }
      unset($p);
      blog_write($posts);
    }
    header('Location: blog.php?view=post&id=' . urlencode($id) . '#comments'); exit;
  }

  if ($csrfOk && $action === 'comment_delete' && blog_logged_in()) {
    $id = (string)($_POST['id'] ?? '');
    $cid = (string)($_POST['cid'] ?? '');
    $posts = blog_read();
    foreach ($posts as &$p) {
      if (($p['id'] ?? '') === $id) {
        $p['comments'] = array_values(array_filter($p['comments'] ?? [], function ($c) use ($cid) {
          if (($c['id'] ?? '') !== $cid) return true;
          return !blog_can_edit([$c['author_uid'] ?? '__none__']);
        }));
        break;
      }
    }
    unset($p);
    blog_write($posts);
    header('Location: blog.php?view=post&id=' . urlencode($id) . '#comments'); exit;
  }
}

/* ── 화면 ── */
$PAGE_TITLE = '블로그';
$ACTIVE     = 'blog';
require __DIR__ . '/_header.php';
if (!function_exists('h')) { function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$view = $_GET['view'] ?? 'list';
$allPosts = blog_read();

function blog_find(array $posts, string $id): ?array {
  foreach ($posts as $p) if (($p['id'] ?? '') === $id) return $p;
  return null;
}
function blog_excerpt(string $body, int $len = 88): string {
  $flat = preg_replace('/\s+/u', ' ', trim($body));
  $s = mb_substr($flat, 0, $len);
  return $s . (mb_strlen($flat) > $len ? '…' : '');
}
function blog_readmin(string $body): int {
  $chars = mb_strlen(preg_replace('/\s+/u', '', $body));
  return max(1, (int)ceil($chars / 350));
}
function blog_date(string $s, string $fmt = 'Y.m.d'): string {
  $t = strtotime($s); return $t ? date($fmt, $t) : $s;
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@600;700;900&display=swap" rel="stylesheet">
<style>
  :root{
    --jp-paper:#f5f7fb; --jp-card:#ffffff; --jp-line:#e3e8f1;
    --jp-ink:#101826; --jp-ink-soft:#55607a; --jp-ink-faint:#8b95ab;
    --jp-brand:#2454d6; --jp-brand-soft:#eaf0fe;
    --jp-flag:#f2a73b; --jp-flag-soft:#fdf1dd;
    --jp-danger:#d64545; --jp-danger-soft:#fdeceb;
  }
  .jrnl{max-width:900px;margin:0 auto;padding:0 20px 90px;color:var(--jp-ink)}
  .jrnl-serif{font-family:'Noto Serif KR',serif}

  /* ── 히어로 ── */
  .jrnl-hero{padding:38px 0 26px;border-bottom:1px solid var(--jp-line);margin-bottom:28px}
  .jrnl-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;
    color:var(--jp-brand);letter-spacing:.06em;margin-bottom:10px}
  .jrnl-eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--jp-flag)}
  .jrnl-hero h1{font-size:clamp(28px,5vw,40px);font-weight:900;line-height:1.15;letter-spacing:-.01em;margin-bottom:9px}
  .jrnl-hero p{font-size:14px;color:var(--jp-ink-soft);line-height:1.7;max-width:520px;margin-bottom:22px}
  .jrnl-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .jrnl-search{flex:1;min-width:200px;display:flex;align-items:center;gap:8px;
    background:var(--jp-card);border:1px solid var(--jp-line);border-radius:11px;padding:10px 14px}
  .jrnl-search input{border:0;outline:0;background:transparent;font-size:13.5px;width:100%;font-family:inherit;color:var(--jp-ink)}
  .jrnl-search svg{flex-shrink:0;opacity:.45}
  .jrnl-write{display:inline-flex;align-items:center;gap:7px;background:var(--jp-ink);color:#fff;
    border:0;border-radius:11px;padding:11px 18px;font-size:13.5px;font-weight:700;text-decoration:none;
    white-space:nowrap;transition:.15s}
  .jrnl-write:hover{background:var(--jp-brand);transform:translateY(-1px)}
  .jrnl-write b{color:var(--jp-flag)}

  .jrnl-cats{display:flex;gap:7px;flex-wrap:wrap;margin-top:14px}
  .jrnl-chip{font-size:12px;font-weight:600;padding:6px 13px;border-radius:999px;
    border:1px solid var(--jp-line);color:var(--jp-ink-soft);text-decoration:none;transition:.14s}
  .jrnl-chip:hover{border-color:var(--jp-brand);color:var(--jp-brand)}
  .jrnl-chip.on{background:var(--jp-ink);border-color:var(--jp-ink);color:#fff}

  /* ── 목록: 로그북 레일 ── */
  .jrnl-log{position:relative}
  .jrnl-entry{position:relative;display:grid;grid-template-columns:74px 1fr;gap:18px;padding-bottom:22px}
  .jrnl-entry::before{content:'';position:absolute;left:36px;top:26px;bottom:0;width:1px;background:var(--jp-line)}
  .jrnl-entry:last-child::before{display:none}
  .jrnl-rail{display:flex;flex-direction:column;align-items:center;padding-top:4px}
  .jrnl-dot{width:9px;height:9px;border-radius:50%;background:var(--jp-brand);border:2px solid var(--jp-paper);
    box-shadow:0 0 0 1px var(--jp-line);margin-bottom:6px;flex-shrink:0}
  .jrnl-dot.pinned{background:var(--jp-flag)}
  .jrnl-stamp{font-family:ui-monospace,'SF Mono',Consolas,monospace;font-size:10.5px;color:var(--jp-ink-faint);
    text-align:center;line-height:1.5;white-space:nowrap}

  .jrnl-card{position:relative;display:block;background:var(--jp-card);border:1px solid var(--jp-line);
    border-radius:14px;padding:18px 20px;text-decoration:none;color:inherit;transition:.15s}
  .jrnl-card:hover{border-color:#c9d4ea;box-shadow:0 6px 20px rgba(16,24,38,.06);transform:translateY(-1px)}
  .jrnl-flag{position:absolute;top:0;right:16px;background:var(--jp-flag);color:#fff;font-size:10px;
    font-weight:800;padding:4px 9px 5px;border-radius:0 0 6px 6px;letter-spacing:.02em}
  .jrnl-card__top{display:flex;align-items:center;gap:8px;margin-bottom:7px}
  .jrnl-cat{font-size:10.5px;font-weight:800;color:var(--jp-brand);background:var(--jp-brand-soft);
    padding:2px 9px;border-radius:999px}
  .jrnl-card h3{font-family:'Noto Serif KR',serif;font-size:17.5px;font-weight:700;line-height:1.4;margin-bottom:6px}
  .jrnl-card p{font-size:13px;color:var(--jp-ink-soft);line-height:1.65;margin-bottom:11px}
  .jrnl-meta{display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--jp-ink-faint)}
  .jrnl-meta b{color:var(--jp-ink-soft);font-weight:600}
  .jrnl-meta .sep{opacity:.5}

  .jrnl-empty{text-align:center;padding:60px 20px;color:var(--jp-ink-faint)}
  .jrnl-empty__ico{font-size:30px;margin-bottom:10px}
  .jrnl-empty h3{font-family:'Noto Serif KR',serif;font-size:17px;color:var(--jp-ink-soft);margin-bottom:6px}
  .jrnl-empty p{font-size:13px;line-height:1.7}

  /* ── 상세 ── */
  .jrnl-back{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;color:var(--jp-ink-soft);
    text-decoration:none;margin-bottom:20px}
  .jrnl-back:hover{color:var(--jp-brand)}
  .jrnl-detail-top{padding-bottom:22px;border-bottom:1px solid var(--jp-line);margin-bottom:26px}
  .jrnl-detail-top .jrnl-cat{margin-bottom:12px;display:inline-block}
  .jrnl-detail-top h1{font-family:'Noto Serif KR',serif;font-size:clamp(24px,4vw,32px);font-weight:900;
    line-height:1.35;margin-bottom:16px}
  .jrnl-author{display:flex;align-items:center;gap:10px}
  .jrnl-avatar{width:34px;height:34px;border-radius:50%;background:var(--jp-ink);color:#fff;
    display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
  .jrnl-author__name{font-size:13.5px;font-weight:700}
  .jrnl-author__meta{font-size:11.5px;color:var(--jp-ink-faint)}
  .jrnl-owner-actions{margin-left:auto;display:flex;gap:6px}
  .jrnl-iconbtn{border:1px solid var(--jp-line);background:var(--jp-card);color:var(--jp-ink-soft);
    border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit}
  .jrnl-iconbtn:hover{border-color:var(--jp-brand);color:var(--jp-brand)}
  .jrnl-iconbtn.danger:hover{border-color:var(--jp-danger);color:var(--jp-danger);background:var(--jp-danger-soft)}

  .jrnl-body{font-size:15px;line-height:1.9;color:#22293a;white-space:pre-wrap;word-break:break-word;margin-bottom:36px}

  /* ── 댓글 ── */
  .jrnl-csec h2{font-family:'Noto Serif KR',serif;font-size:17px;font-weight:700;margin-bottom:16px}
  .jrnl-cform{display:flex;gap:10px;margin-bottom:22px}
  .jrnl-cform textarea{flex:1;border:1px solid var(--jp-line);border-radius:11px;padding:11px 14px;
    font-size:13.5px;font-family:inherit;resize:none;min-height:44px;color:var(--jp-ink)}
  .jrnl-cform textarea:focus{outline:2px solid var(--jp-brand);outline-offset:1px;border-color:transparent}
  .jrnl-cform button{flex-shrink:0;background:var(--jp-ink);color:#fff;border:0;border-radius:11px;
    padding:0 18px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
  .jrnl-cform button:hover{background:var(--jp-brand)}
  .jrnl-clogin{background:var(--jp-brand-soft);border:1px solid #d3e0fb;border-radius:11px;
    padding:14px 16px;font-size:13px;color:var(--jp-brand);margin-bottom:22px;text-align:center}
  .jrnl-clogin a{color:inherit;font-weight:700;text-decoration:underline}

  .jrnl-comment{display:flex;gap:11px;padding:14px 0;border-bottom:1px solid var(--jp-line)}
  .jrnl-comment:last-child{border-bottom:0}
  .jrnl-comment__av{width:28px;height:28px;border-radius:50%;background:var(--jp-brand-soft);color:var(--jp-brand);
    display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
  .jrnl-comment__body{flex:1;min-width:0}
  .jrnl-comment__top{display:flex;align-items:center;gap:7px;margin-bottom:4px}
  .jrnl-comment__name{font-size:12.5px;font-weight:700}
  .jrnl-comment__time{font-size:11px;color:var(--jp-ink-faint)}
  .jrnl-comment__text{font-size:13.5px;line-height:1.7;color:#2c3448;white-space:pre-wrap;word-break:break-word}
  .jrnl-comment__del{margin-left:auto;background:none;border:0;color:var(--jp-ink-faint);cursor:pointer;
    font-size:11px;font-family:inherit;padding:2px 6px}
  .jrnl-comment__del:hover{color:var(--jp-danger)}
  .jrnl-cempty{font-size:13px;color:var(--jp-ink-faint);text-align:center;padding:22px 0}

  /* ── 글쓰기 폼 ── */
  .jrnl-form-card{background:var(--jp-card);border:1px solid var(--jp-line);border-radius:16px;padding:26px}
  .jrnl-form-card h1{font-family:'Noto Serif KR',serif;font-size:22px;font-weight:800;margin-bottom:20px}
  .jrnl-field{margin-bottom:18px}
  .jrnl-field label{display:block;font-size:12px;font-weight:700;color:var(--jp-ink-soft);margin-bottom:7px}
  .jrnl-field input[type=text]{width:100%;border:1px solid var(--jp-line);border-radius:10px;
    padding:12px 14px;font-size:16px;font-family:'Noto Serif KR',serif;font-weight:700;color:var(--jp-ink)}
  .jrnl-field textarea{width:100%;border:1px solid var(--jp-line);border-radius:10px;padding:13px 14px;
    font-size:14px;line-height:1.8;font-family:inherit;color:var(--jp-ink);resize:vertical;min-height:260px}
  .jrnl-field input:focus,.jrnl-field textarea:focus{outline:2px solid var(--jp-brand);outline-offset:1px}
  .jrnl-catpick{display:flex;gap:8px;flex-wrap:wrap}
  .jrnl-catpick label{display:inline-flex;align-items:center;font-size:12px;font-weight:600;
    padding:7px 14px;border-radius:999px;border:1px solid var(--jp-line);cursor:pointer;color:var(--jp-ink-soft)}
  .jrnl-catpick input{display:none}
  .jrnl-catpick input:checked + span{color:#fff}
  .jrnl-catpick label:has(input:checked){background:var(--jp-ink);border-color:var(--jp-ink);color:#fff}
  .jrnl-catpick label.jrnl-picked{background:var(--jp-ink);border-color:var(--jp-ink);color:#fff}
  .jrnl-pin{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--jp-ink-soft)}
  .jrnl-form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
  .jrnl-btn-ghost{background:var(--jp-card);border:1px solid var(--jp-line);color:var(--jp-ink-soft);
    border-radius:10px;padding:11px 20px;font-size:13.5px;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit}
  .jrnl-btn-primary{background:var(--jp-ink);border:0;color:#fff;border-radius:10px;padding:11px 22px;
    font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit}
  .jrnl-btn-primary:hover{background:var(--jp-brand)}
  .jrnl-err{background:var(--jp-danger-soft);color:var(--jp-danger);border-radius:9px;padding:10px 14px;
    font-size:12.5px;margin-bottom:16px}

  .jrnl-pager{display:flex;justify-content:center;gap:8px;margin-top:8px}
  .jrnl-pager a,.jrnl-pager span{display:inline-flex;align-items:center;justify-content:center;
    min-width:34px;height:34px;border-radius:9px;border:1px solid var(--jp-line);font-size:12.5px;
    text-decoration:none;color:var(--jp-ink-soft)}
  .jrnl-pager a:hover{border-color:var(--jp-brand);color:var(--jp-brand)}
  .jrnl-pager .cur{background:var(--jp-ink);border-color:var(--jp-ink);color:#fff;font-weight:700}

  @media (max-width:600px){
    .jrnl-entry{grid-template-columns:44px 1fr;gap:10px}
    .jrnl-entry::before{left:21px}
    .jrnl-stamp{display:none}
    .jrnl-author__meta,.jrnl-owner-actions .jrnl-iconbtn span{display:none}
    .jrnl-form-card{padding:20px 16px}
  }
</style>

<div class="jrnl">

<?php if ($view === 'post'):
  $id = (string)($_GET['id'] ?? '');
  $post = blog_find($allPosts, $id);
  if (!$post): ?>
    <div class="jrnl-empty">
      <div class="jrnl-empty__ico">🔍</div>
      <h3>글을 찾을 수 없습니다</h3>
      <p>삭제되었거나 잘못된 주소입니다.</p>
      <div style="margin-top:16px"><a class="jrnl-write" href="blog.php">← 목록으로</a></div>
    </div>
  <?php else:
    $canEdit = blog_can_edit([$post['author_uid'] ?? '__none__']);
    $ini = mb_substr($post['author_name'] ?? '?', 0, 1);
  ?>
    <a class="jrnl-back" href="blog.php">← 목록으로</a>

    <div class="jrnl-detail-top">
      <span class="jrnl-cat"><?=h($post['cat'] ?? '일반')?></span>
      <h1><?=h($post['title'] ?? '')?></h1>
      <div class="jrnl-author">
        <div class="jrnl-avatar"><?=h($ini)?></div>
        <div>
          <div class="jrnl-author__name"><?=h($post['author_name'] ?? '익명')?></div>
          <div class="jrnl-author__meta"><?=h(blog_date($post['created_at'] ?? '', 'Y.m.d H:i'))?>
            · 약 <?=blog_readmin($post['body'] ?? '')?>분 분량</div>
        </div>
        <?php if ($canEdit): ?>
          <div class="jrnl-owner-actions">
            <a class="jrnl-iconbtn" href="blog.php?view=edit&id=<?=urlencode($post['id'])?>">✎ <span>수정</span></a>
            <form method="post" onsubmit="return confirm('이 글을 삭제할까요? 되돌릴 수 없습니다.')" style="display:inline">
              <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=h($post['id'])?>">
              <button class="jrnl-iconbtn danger" type="submit">🗑 <span>삭제</span></button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="jrnl-body"><?=nl2br(h($post['body'] ?? ''))?></div>

    <div class="jrnl-csec" id="comments">
      <h2>댓글 <?=count($post['comments'] ?? [])?></h2>

      <?php if (blog_logged_in()): ?>
        <form class="jrnl-cform" method="post">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="comment_add">
          <input type="hidden" name="id" value="<?=h($post['id'])?>">
          <textarea name="text" placeholder="댓글을 남겨보세요" required></textarea>
          <button type="submit">등록</button>
        </form>
      <?php else: ?>
        <div class="jrnl-clogin">댓글을 남기려면 <a href="/index.php">로그인</a>이 필요합니다.</div>
      <?php endif; ?>

      <?php $cs = $post['comments'] ?? []; if (!$cs): ?>
        <div class="jrnl-cempty">아직 댓글이 없습니다. 첫 댓글을 남겨보세요.</div>
      <?php else: foreach ($cs as $c):
        $cIni = mb_substr($c['author_name'] ?? '?', 0, 1);
        $cCan = blog_can_edit([$c['author_uid'] ?? '__none__']);
      ?>
        <div class="jrnl-comment">
          <div class="jrnl-comment__av"><?=h($cIni)?></div>
          <div class="jrnl-comment__body">
            <div class="jrnl-comment__top">
              <span class="jrnl-comment__name"><?=h($c['author_name'] ?? '익명')?></span>
              <span class="jrnl-comment__time"><?=h(blog_date($c['created_at'] ?? '', 'm.d H:i'))?></span>
              <?php if ($cCan): ?>
                <form method="post" onsubmit="return confirm('댓글을 삭제할까요?')" style="margin-left:auto">
                  <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                  <input type="hidden" name="action" value="comment_delete">
                  <input type="hidden" name="id" value="<?=h($post['id'])?>">
                  <input type="hidden" name="cid" value="<?=h($c['id'])?>">
                  <button class="jrnl-comment__del" type="submit">삭제</button>
                </form>
              <?php endif; ?>
            </div>
            <div class="jrnl-comment__text"><?=nl2br(h($c['text'] ?? ''))?></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php endif; ?>

<?php elseif ($view === 'write' || $view === 'edit'):
  if (!blog_logged_in()): ?>
    <div class="jrnl-empty">
      <div class="jrnl-empty__ico">🔒</div>
      <h3>로그인이 필요합니다</h3>
      <p>글을 쓰려면 먼저 로그인해 주세요.</p>
      <div style="margin-top:16px"><a class="jrnl-write" href="/index.php">로그인하러 가기</a></div>
    </div>
  <?php else:
    $editing = ($view === 'edit');
    $post = $editing ? blog_find($allPosts, (string)($_GET['id'] ?? '')) : null;
    if ($editing && (!$post || !blog_can_edit([$post['author_uid'] ?? '__none__']))): ?>
      <div class="jrnl-empty">
        <div class="jrnl-empty__ico">🚫</div>
        <h3>수정할 수 없습니다</h3>
        <p>본인이 쓴 글만 수정할 수 있습니다.</p>
        <div style="margin-top:16px"><a class="jrnl-write" href="blog.php">← 목록으로</a></div>
      </div>
    <?php else: ?>
      <div class="jrnl-form-card">
        <h1><?=$editing ? '글 수정' : '새 글 쓰기'?></h1>
        <?php if (($_GET['err'] ?? '') === 'empty'): ?>
          <div class="jrnl-err">제목과 내용을 모두 입력해 주세요.</div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="<?=$editing ? 'update' : 'create'?>">
          <?php if ($editing): ?><input type="hidden" name="id" value="<?=h($post['id'])?>"><?php endif; ?>

          <div class="jrnl-field">
            <label>분류</label>
            <div class="jrnl-catpick">
              <?php $cats = BLOG_CATS; if (blog_is_admin()) $cats[] = '공지';
                $curCat = $editing ? ($post['cat'] ?? '일반') : '일반';
                foreach ($cats as $c): ?>
                <label><input type="radio" name="cat" value="<?=h($c)?>" <?=$c===$curCat?'checked':''?>><span><?=h($c)?></span></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="jrnl-field">
            <label>제목</label>
            <input type="text" name="title" maxlength="120" required
              placeholder="제목을 적어주세요" value="<?=h($editing ? ($post['title'] ?? '') : '')?>">
          </div>

          <div class="jrnl-field">
            <label>내용</label>
            <textarea name="body" required placeholder="현장에서 겪은 일, 팁, 궁금한 점을 자유롭게 적어주세요."><?=h($editing ? ($post['body'] ?? '') : '')?></textarea>
          </div>

          <?php if (blog_is_admin()): ?>
            <div class="jrnl-field">
              <label class="jrnl-pin">
                <input type="checkbox" name="pinned" <?=($editing && !empty($post['pinned']))?'checked':''?>>
                상단 고정
              </label>
            </div>
          <?php endif; ?>

          <div class="jrnl-form-actions">
            <a class="jrnl-btn-ghost" href="<?=$editing ? ('blog.php?view=post&id='.urlencode($post['id'])) : 'blog.php'?>">취소</a>
            <button class="jrnl-btn-primary" type="submit"><?=$editing ? '수정 완료' : '올리기'?></button>
          </div>
        </form>
      </div>
    <?php endif; endif; ?>

<script>
/* :has() 미지원 브라우저 대비 — 분류 선택 시 강조를 확실히 해줍니다 */
document.querySelectorAll('.jrnl-catpick input[type=radio]').forEach(function(r){
  r.addEventListener('change', function(){
    document.querySelectorAll('.jrnl-catpick label').forEach(function(l){ l.classList.remove('jrnl-picked'); });
    if (r.checked) r.closest('label').classList.add('jrnl-picked');
  });
  if (r.checked) r.closest('label').classList.add('jrnl-picked');
});
</script>

<?php else:
  /* ── 목록 ── */
  $q   = trim((string)($_GET['q'] ?? ''));
  $cat = (string)($_GET['cat'] ?? '');
  $page = max(1, (int)($_GET['p'] ?? 1));
  $perPage = 8;

  $filtered = $allPosts;
  if ($cat !== '' && in_array($cat, array_merge(BLOG_CATS, ['공지']), true)) {
    $filtered = array_filter($filtered, fn($p) => ($p['cat'] ?? '') === $cat);
  }
  if ($q !== '') {
    $filtered = array_filter($filtered, function ($p) use ($q) {
      return mb_stripos($p['title'] ?? '', $q) !== false || mb_stripos($p['body'] ?? '', $q) !== false;
    });
  }
  $filtered = array_values($filtered);
  usort($filtered, function ($a, $b) {
    $pa = !empty($a['pinned']); $pb = !empty($b['pinned']);
    if ($pa !== $pb) return $pb <=> $pa;
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
  });

  $total = count($filtered);
  $pages = max(1, (int)ceil($total / $perPage));
  $page = min($page, $pages);
  $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);
  $qs = fn($extra) => http_build_query(array_merge(['q' => $q ?: null, 'cat' => $cat ?: null], $extra));
?>
  <div class="jrnl-hero">
    <div class="jrnl-eyebrow"><span class="dot"></span>YEOHUB COMMUNITY</div>
    <h1 class="jrnl-serif">현장의 기록</h1>
    <p>소방안전관리자들이 남긴 현장 이야기, 실무 팁, 그리고 서로 묻고 답한 질문들입니다.</p>
    <div class="jrnl-toolbar">
      <form class="jrnl-search" method="get">
        <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?=h($cat)?>"><?php endif; ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="text" name="q" value="<?=h($q)?>" placeholder="제목이나 내용으로 검색">
      </form>
      <?php if (blog_logged_in()): ?>
        <a class="jrnl-write" href="blog.php?view=write">✎ <b>+</b> 새 글 쓰기</a>
      <?php else: ?>
        <a class="jrnl-write" href="/index.php">로그인하고 글쓰기</a>
      <?php endif; ?>
    </div>
    <div class="jrnl-cats">
      <a class="jrnl-chip<?=$cat===''?' on':''?>" href="blog.php?<?=$qs([])?>">전체</a>
      <?php foreach (BLOG_CATS as $c): ?>
        <a class="jrnl-chip<?=$cat===$c?' on':''?>" href="blog.php?<?=$qs(['cat'=>$c])?>"><?=h($c)?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$slice): ?>
    <div class="jrnl-empty">
      <div class="jrnl-empty__ico">📭</div>
      <h3><?=$q !== '' ? '검색 결과가 없습니다' : '아직 글이 없습니다'?></h3>
      <p><?=$q !== '' ? '다른 검색어로 다시 찾아보세요.' : '가장 먼저 현장 이야기를 남겨보세요.'?></p>
    </div>
  <?php else: ?>
    <div class="jrnl-log">
      <?php foreach ($slice as $p): ?>
        <div class="jrnl-entry">
          <div class="jrnl-rail">
            <div class="jrnl-dot<?=!empty($p['pinned'])?' pinned':''?>"></div>
            <div class="jrnl-stamp"><?=h(blog_date($p['created_at'] ?? '', 'Y.m.d'))?></div>
          </div>
          <a class="jrnl-card" href="blog.php?view=post&id=<?=urlencode($p['id'])?>">
            <?php if (!empty($p['pinned'])): ?><span class="jrnl-flag">고정</span><?php endif; ?>
            <div class="jrnl-card__top"><span class="jrnl-cat"><?=h($p['cat'] ?? '일반')?></span></div>
            <h3><?=h($p['title'] ?? '')?></h3>
            <p><?=h(blog_excerpt($p['body'] ?? ''))?></p>
            <div class="jrnl-meta">
              <b><?=h($p['author_name'] ?? '익명')?></b><span class="sep">·</span>
              댓글 <?=count($p['comments'] ?? [])?><span class="sep">·</span>
              약 <?=blog_readmin($p['body'] ?? '')?>분
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <div class="jrnl-pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?><span class="cur"><?=$i?></span>
          <?php else: ?><a href="blog.php?<?=$qs(['p'=>$i])?>"><?=$i?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
