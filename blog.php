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
<style>
/* 블로그 전용 — service.php 와 같은 방식: 기존 카드/랩 스타일 위에 최소한만 더합니다 */
.blg-eyebrow{margin-bottom:10px}
.blg-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:18px}
.blg-search{flex:1;min-width:200px;display:flex;align-items:center;gap:8px;
  background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:9px 13px}
.blg-search input{border:0;outline:0;background:transparent;font-size:13.5px;width:100%;
  font-family:inherit;color:var(--fg)}
.blg-cats{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}
.blg-chip{font-size:12px;font-weight:600;padding:6px 13px;border-radius:999px;
  border:1px solid var(--bd);color:var(--mut);text-decoration:none}
.blg-chip:hover{border-color:var(--brand);color:var(--brand2)}
.blg-chip.on{background:var(--fg);border-color:var(--fg);color:#fff}

.blg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:20px}
.blg-card{display:flex;flex-direction:column;text-decoration:none;color:inherit;position:relative}
.blg-card:hover{border-color:var(--brand)}
.blg-tag{display:inline-flex;align-self:flex-start;font-size:10.5px;font-weight:800;
  padding:3px 9px;border-radius:999px;margin-bottom:9px}
.blg-card h3{margin:0 0 6px;font-size:15px;line-height:1.4}
.blg-card p{margin:0 0 12px;font-size:13px;line-height:1.65;opacity:.8;flex:1}
.blg-meta{display:flex;align-items:center;gap:6px;font-size:11.5px;opacity:.65}
.blg-meta b{opacity:1}
.blg-pin{position:absolute;top:12px;right:12px;font-size:15px}

.blg-empty{text-align:center;padding:60px 20px;opacity:.6}
.blg-empty .ico{font-size:30px;margin-bottom:10px}

.blg-pager{display:flex;justify-content:center;gap:8px;margin-top:24px}
.blg-pager a,.blg-pager span{display:inline-flex;align-items:center;justify-content:center;
  min-width:34px;height:34px;border-radius:9px;border:1px solid var(--bd);font-size:12.5px;
  text-decoration:none;color:var(--mut)}
.blg-pager a:hover{border-color:var(--brand);color:var(--brand2)}
.blg-pager .cur{background:var(--fg);border-color:var(--fg);color:#fff;font-weight:700}

/* 상세 */
.blg-back{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;color:var(--mut);
  text-decoration:none;margin-bottom:16px}
.blg-back:hover{color:var(--brand2)}
.blg-detail-top{padding-bottom:20px;border-bottom:1px solid var(--bd);margin-bottom:22px}
.blg-detail-top h1{font-size:24px;margin:12px 0 14px;line-height:1.35}
.blg-author{display:flex;align-items:center;gap:10px}
.blg-avatar{width:32px;height:32px;border-radius:50%;background:var(--brand);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;flex-shrink:0}
.blg-author__name{font-size:13px;font-weight:700}
.blg-author__meta{font-size:11.5px;opacity:.65}
.blg-owner-actions{margin-left:auto;display:flex;gap:6px}
.blg-iconbtn{border:1px solid var(--bd2);background:var(--card);color:var(--mut);
  border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit}
.blg-iconbtn:hover{border-color:var(--brand);color:var(--brand2)}
.blg-iconbtn.danger:hover{border-color:#dc2626;color:#dc2626}
.blg-body{font-size:15px;line-height:1.9;white-space:pre-wrap;word-break:break-word;margin-bottom:32px}

/* 댓글 */
.blg-csec h2{font-size:16px;margin-bottom:14px}
.blg-cform{display:flex;gap:10px;margin-bottom:20px}
.blg-cform textarea{flex:1;border:1px solid var(--bd2);border-radius:10px;padding:10px 13px;
  font-size:13.5px;font-family:inherit;resize:none;min-height:44px;color:var(--fg);background:var(--bg2)}
.blg-cform button{flex-shrink:0;background:var(--brand);color:#fff;border:0;border-radius:10px;
  padding:0 18px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.blg-clogin{background:var(--bg2);border:1px solid var(--bd);border-radius:10px;
  padding:13px 15px;font-size:13px;color:var(--mut);margin-bottom:20px;text-align:center}
.blg-clogin a{color:var(--brand2);font-weight:700;text-decoration:underline}
.blg-comment{display:flex;gap:10px;padding:13px 0;border-bottom:1px solid var(--bd)}
.blg-comment:last-child{border-bottom:0}
.blg-comment__av{width:26px;height:26px;border-radius:50%;background:var(--bg2);color:var(--brand2);
  display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.blg-comment__top{display:flex;align-items:center;gap:7px;margin-bottom:4px}
.blg-comment__name{font-size:12.5px;font-weight:700}
.blg-comment__time{font-size:11px;opacity:.6}
.blg-comment__text{font-size:13.5px;line-height:1.7;white-space:pre-wrap;word-break:break-word}
.blg-comment__del{margin-left:auto;background:none;border:0;color:var(--mut);cursor:pointer;
  font-size:11px;font-family:inherit}
.blg-comment__del:hover{color:#dc2626}
.blg-cempty{font-size:13px;opacity:.6;text-align:center;padding:20px 0}

/* 글쓰기 폼 */
.blg-field{margin-bottom:16px}
.blg-field label{display:block;font-size:12px;font-weight:700;opacity:.7;margin-bottom:7px}
.blg-field input[type=text]{width:100%;border:1px solid var(--bd2);border-radius:9px;
  padding:11px 13px;font-size:15px;font-weight:700;font-family:inherit;color:var(--fg);background:var(--bg2)}
.blg-field textarea{width:100%;border:1px solid var(--bd2);border-radius:9px;padding:12px 13px;
  font-size:14px;line-height:1.8;font-family:inherit;color:var(--fg);resize:vertical;
  min-height:240px;background:var(--bg2)}
.blg-catpick{display:flex;gap:8px;flex-wrap:wrap}
.blg-catpick label{display:inline-flex;align-items:center;font-size:12px;font-weight:600;
  padding:7px 14px;border-radius:999px;border:1px solid var(--bd2);cursor:pointer;color:var(--mut)}
.blg-catpick input{display:none}
.blg-catpick label.picked{background:var(--fg);border-color:var(--fg);color:#fff}
.blg-pin-chk{display:flex;align-items:center;gap:8px;font-size:12.5px;opacity:.75}
.blg-form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
.blg-err{background:#fdeceb;color:#b91c1c;border-radius:9px;padding:10px 14px;font-size:12.5px;margin-bottom:16px}
</style>

<?php
/* 분류마다 고유 색을 준다 — 목록을 스캔할 때 색만 보고도 어떤 글인지 짐작되게 */
function blog_cat_color(string $cat): array {
  $map = [
    '일반'      => ['bg' => 'var(--bg2)', 'fg' => 'var(--mut2)'],
    '현장 꿀팁' => ['bg' => '#e3f6fa', 'fg' => '#0891b2'],
    '질문'      => ['bg' => '#eaf0fe', 'fg' => 'var(--brand2)'],
    '후기'      => ['bg' => '#fff1e8', 'fg' => '#ea580c'],
    '공지'      => ['bg' => '#fdeceb', 'fg' => '#dc2626'],
  ];
  return $map[$cat] ?? $map['일반'];
}
function blog_tag(string $cat): string {
  $c = blog_cat_color($cat);
  return '<span class="blg-tag" style="background:' . $c['bg'] . ';color:' . $c['fg'] . '">' . h($cat) . '</span>';
}
?>

<?php if ($view === 'post'):
  $id = (string)($_GET['id'] ?? '');
  $post = blog_find($allPosts, $id);
?>
<header class="page-head">
  <div class="page-head__inner">
    <a class="blg-back" href="/blog.php">← 목록으로</a>
    <div class="page-head__label"><span></span> 현장의 기록</div>
    <h1><?= $post ? h($post['title'] ?? '') : '글을 찾을 수 없습니다' ?></h1>
  </div>
</header>
<main class="wrap">
  <?php if (!$post): ?>
    <div class="blg-empty">
      <div class="ico">🔍</div>
      <p>삭제되었거나 잘못된 주소입니다.</p>
    </div>
  <?php else:
    $canEdit = blog_can_edit([$post['author_uid'] ?? '__none__']);
    $ini = mb_substr($post['author_name'] ?? '?', 0, 1);
  ?>
    <div class="card">
      <div class="blg-detail-top">
        <?=blog_tag($post['cat'] ?? '일반')?>
        <div class="blg-author">
          <div class="blg-avatar"><?=h($ini)?></div>
          <div>
            <div class="blg-author__name"><?=h($post['author_name'] ?? '익명')?></div>
            <div class="blg-author__meta"><?=h(blog_date($post['created_at'] ?? '', 'Y.m.d H:i'))?>
              · 약 <?=blog_readmin($post['body'] ?? '')?>분 분량</div>
          </div>
          <?php if ($canEdit): ?>
            <div class="blg-owner-actions">
              <a class="blg-iconbtn" href="/blog.php?view=edit&id=<?=urlencode($post['id'])?>">✎ 수정</a>
              <form method="post" onsubmit="return confirm('이 글을 삭제할까요? 되돌릴 수 없습니다.')" style="display:inline">
                <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=h($post['id'])?>">
                <button class="blg-iconbtn danger" type="submit">🗑 삭제</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="blg-body"><?=nl2br(h($post['body'] ?? ''))?></div>

      <div class="blg-csec" id="comments">
        <h2>댓글 <?=count($post['comments'] ?? [])?></h2>

        <?php if (blog_logged_in()): ?>
          <form class="blg-cform" method="post">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input type="hidden" name="action" value="comment_add">
            <input type="hidden" name="id" value="<?=h($post['id'])?>">
            <textarea name="text" placeholder="댓글을 남겨보세요" required></textarea>
            <button type="submit">등록</button>
          </form>
        <?php else: ?>
          <div class="blg-clogin">댓글을 남기려면 <a href="/index.php">로그인</a>이 필요합니다.</div>
        <?php endif; ?>

        <?php $cs = $post['comments'] ?? []; if (!$cs): ?>
          <div class="blg-cempty">아직 댓글이 없습니다. 첫 댓글을 남겨보세요.</div>
        <?php else: foreach ($cs as $c):
          $cIni = mb_substr($c['author_name'] ?? '?', 0, 1);
          $cCan = blog_can_edit([$c['author_uid'] ?? '__none__']);
        ?>
          <div class="blg-comment">
            <div class="blg-comment__av"><?=h($cIni)?></div>
            <div style="flex:1;min-width:0">
              <div class="blg-comment__top">
                <span class="blg-comment__name"><?=h($c['author_name'] ?? '익명')?></span>
                <span class="blg-comment__time"><?=h(blog_date($c['created_at'] ?? '', 'm.d H:i'))?></span>
                <?php if ($cCan): ?>
                  <form method="post" onsubmit="return confirm('댓글을 삭제할까요?')" style="margin-left:auto">
                    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                    <input type="hidden" name="action" value="comment_delete">
                    <input type="hidden" name="id" value="<?=h($post['id'])?>">
                    <input type="hidden" name="cid" value="<?=h($c['id'])?>">
                    <button class="blg-comment__del" type="submit">삭제</button>
                  </form>
                <?php endif; ?>
              </div>
              <div class="blg-comment__text"><?=nl2br(h($c['text'] ?? ''))?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php elseif ($view === 'write' || $view === 'edit'):
  if (!blog_logged_in()): ?>
<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 현장의 기록</div>
    <h1>로그인이 필요합니다</h1>
    <p>글을 쓰려면 먼저 로그인해 주세요.</p>
  </div>
</header>
<main class="wrap">
  <div class="blg-empty"><div class="ico">🔒</div><a class="btn btn--primary" href="/index.php">로그인하러 가기</a></div>
</main>
  <?php else:
    $editing = ($view === 'edit');
    $post = $editing ? blog_find($allPosts, (string)($_GET['id'] ?? '')) : null;
    if ($editing && (!$post || !blog_can_edit([$post['author_uid'] ?? '__none__']))): ?>
<header class="page-head">
  <div class="page-head__inner"><h1>수정할 수 없습니다</h1><p>본인이 쓴 글만 수정할 수 있습니다.</p></div>
</header>
<main class="wrap"><div class="blg-empty"><div class="ico">🚫</div><a class="btn" href="/blog.php">← 목록으로</a></div></main>
    <?php else: ?>
<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 현장의 기록</div>
    <h1><?=$editing ? '글 수정' : '새 글 쓰기'?></h1>
    <p>소방안전관리자들과 현장 이야기, 실무 팁, 궁금한 점을 나눠보세요.</p>
  </div>
</header>
<main class="wrap">
  <div class="card">
    <?php if (($_GET['err'] ?? '') === 'empty'): ?>
      <div class="blg-err">제목과 내용을 모두 입력해 주세요.</div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="<?=$editing ? 'update' : 'create'?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?=h($post['id'])?>"><?php endif; ?>

      <div class="blg-field">
        <label>분류</label>
        <div class="blg-catpick">
          <?php $cats = BLOG_CATS; if (blog_is_admin()) $cats[] = '공지';
            $curCat = $editing ? ($post['cat'] ?? '일반') : '일반';
            foreach ($cats as $c): ?>
            <label><input type="radio" name="cat" value="<?=h($c)?>" <?=$c===$curCat?'checked':''?>><span><?=h($c)?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="blg-field">
        <label>제목</label>
        <input type="text" name="title" maxlength="120" required
          placeholder="제목을 적어주세요" value="<?=h($editing ? ($post['title'] ?? '') : '')?>">
      </div>

      <div class="blg-field">
        <label>내용</label>
        <textarea name="body" required placeholder="현장에서 겪은 일, 팁, 궁금한 점을 자유롭게 적어주세요."><?=h($editing ? ($post['body'] ?? '') : '')?></textarea>
      </div>

      <?php if (blog_is_admin()): ?>
        <div class="blg-field">
          <label class="blg-pin-chk">
            <input type="checkbox" name="pinned" <?=($editing && !empty($post['pinned']))?'checked':''?>>
            상단 고정
          </label>
        </div>
      <?php endif; ?>

      <div class="blg-form-actions">
        <a class="btn" href="<?=$editing ? ('/blog.php?view=post&id='.urlencode($post['id'])) : '/blog.php'?>">취소</a>
        <button class="btn btn--primary" type="submit"><?=$editing ? '수정 완료' : '올리기'?></button>
      </div>
    </form>
  </div>
</main>
<script>
document.querySelectorAll('.blg-catpick input[type=radio]').forEach(function(r){
  r.addEventListener('change', function(){
    document.querySelectorAll('.blg-catpick label').forEach(function(l){ l.classList.remove('picked'); });
    if (r.checked) r.closest('label').classList.add('picked');
  });
  if (r.checked) r.closest('label').classList.add('picked');
});
</script>
    <?php endif; endif; ?>

<?php else:
  /* ── 목록 ── */
  $q   = trim((string)($_GET['q'] ?? ''));
  $cat = (string)($_GET['cat'] ?? '');
  $page = max(1, (int)($_GET['p'] ?? 1));
  $perPage = 9;

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
<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 현장의 기록</div>
    <h1>블로그</h1>
    <p>소방안전관리자들이 남긴 현장 이야기, 실무 팁, 그리고 서로 묻고 답한 질문들입니다.</p>
    <div class="blg-toolbar">
      <form class="blg-search" method="get">
        <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?=h($cat)?>"><?php endif; ?>
        <input type="text" name="q" value="<?=h($q)?>" placeholder="제목이나 내용으로 검색">
      </form>
      <?php if (blog_logged_in()): ?>
        <a class="btn btn--primary" href="/blog.php?view=write">✎ 새 글 쓰기</a>
      <?php else: ?>
        <a class="btn btn--primary" href="/index.php">로그인하고 글쓰기</a>
      <?php endif; ?>
    </div>
    <div class="blg-cats">
      <a class="blg-chip<?=$cat===''?' on':''?>" href="/blog.php?<?=$qs([])?>">전체</a>
      <?php foreach (BLOG_CATS as $c): ?>
        <a class="blg-chip<?=$cat===$c?' on':''?>" href="/blog.php?<?=$qs(['cat'=>$c])?>"><?=h($c)?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<main class="wrap">
  <?php if (!$slice): ?>
    <div class="blg-empty">
      <div class="ico">📭</div>
      <p><?=$q !== '' ? '검색 결과가 없습니다. 다른 검색어로 다시 찾아보세요.' : '아직 글이 없습니다. 가장 먼저 현장 이야기를 남겨보세요.'?></p>
    </div>
  <?php else: ?>
    <div class="blg-grid">
      <?php foreach ($slice as $p): ?>
        <a class="card blg-card" href="/blog.php?view=post&id=<?=urlencode($p['id'])?>">
          <?php if (!empty($p['pinned'])): ?><span class="blg-pin" title="고정글">📌</span><?php endif; ?>
          <?=blog_tag($p['cat'] ?? '일반')?>
          <h3><?=h($p['title'] ?? '')?></h3>
          <p><?=h(blog_excerpt($p['body'] ?? ''))?></p>
          <div class="blg-meta">
            <b><?=h($p['author_name'] ?? '익명')?></b> ·
            <?=h(blog_date($p['created_at'] ?? ''))?> ·
            댓글 <?=count($p['comments'] ?? [])?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <div class="blg-pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?><span class="cur"><?=$i?></span>
          <?php else: ?><a href="/blog.php?<?=$qs(['p'=>$i])?>"><?=$i?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
