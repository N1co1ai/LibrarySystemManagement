<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

/* ---------- Add / update / delete a book ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $back   = 'books.php';

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
    } elseif ($action === 'delete') {
        $n = $pdo->prepare('SELECT (SELECT COUNT(*) FROM borrow_records WHERE book_id = ?) + (SELECT COUNT(*) FROM reservations WHERE book_id = ?)');
        $n->execute([$id, $id]);
        if ((int) $n->fetchColumn() > 0) {
            flash_set('error', 'This book has borrowing or reservation records, so it cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
            flash_set('success', 'Book deleted.');
        }
    } elseif ($action === 'save') {
        $title  = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $callNo = trim($_POST['call_no'] ?? '');
        $cat    = $_POST['category'] ?? '';
        $copies = (int) ($_POST['copies'] ?? 0);
        $shelf  = trim($_POST['shelf'] ?? '');
        if ($id > 0) { $back = 'books.php?edit=' . $id; }

        if ($title === '' || $author === '' || $callNo === '' || $cat === '') {
            flash_set('error', 'Please fill in the title, author, call number and category.');
        } elseif (strlen($title) > 150 || strlen($author) > 100 || strlen($shelf) > 30) {
            flash_set('error', 'One of the fields is too long.');
        } elseif (!preg_match('/^[A-Za-z0-9 ._\/-]{2,30}$/', $callNo)) {
            flash_set('error', 'The call number can only use letters, numbers, spaces and . _ / - (2 to 30 characters).');
        } elseif (!in_array($cat, BOOK_CATEGORIES, true)) {
            flash_set('error', 'Please choose a category.');
        } elseif ($copies < 1 || $copies > 999) {
            flash_set('error', 'Number of copies must be between 1 and 999.');
        } else {
            try {
                if ($id > 0) {
                    $b = $pdo->prepare('SELECT copies_total, copies_available FROM books WHERE id = ?');
                    $b->execute([$id]);
                    $old = $b->fetch();
                    if (!$old) { throw new RuntimeException('missing'); }
                    $out = (int) $old['copies_total'] - (int) $old['copies_available'];   // copies currently lent out
                    if ($copies < $out) {
                        flash_set('error', "Copies cannot be lower than the $out currently borrowed.");
                        redirect($back);
                    }
                    $pdo->prepare('UPDATE books SET title = ?, author = ?, call_no = ?, category = ?, shelf_location = ?,
                                   copies_total = ?, copies_available = ? WHERE id = ?')
                        ->execute([$title, $author, $callNo, $cat, $shelf === '' ? null : $shelf, $copies, $copies - $out, $id]);
                    flash_set('success', 'Book updated.');
                } else {
                    $pdo->prepare('INSERT INTO books (title, author, call_no, category, shelf_location, copies_total, copies_available)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$title, $author, $callNo, $cat, $shelf === '' ? null : $shelf, $copies, $copies]);
                    flash_set('success', 'Book saved to the catalog.');
                }
                $back = 'books.php';
            } catch (PDOException $e) {
                flash_set('error', ($e->getCode() === '23000') ? 'A book with this call number already exists.' : 'Could not save the book.');
            } catch (RuntimeException $e) {
                flash_set('error', 'That book no longer exists.');
                $back = 'books.php';
            }
        }
    }
    redirect($back);
}

/* ---------- Filters + list ---------- */
$q      = trim($_GET['q'] ?? '');
$cat    = $_GET['cat'] ?? '';
$status = $_GET['status'] ?? '';
$where  = '1 = 1';
$params = [];
if ($q !== '') {
    $where .= ' AND (title LIKE ? OR author LIKE ? OR call_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($cat, BOOK_CATEGORIES, true)) { $where .= ' AND category = ?'; $params[] = $cat; }
if ($status === 'available') { $where .= ' AND copies_available > 0'; }
if ($status === 'loan')      { $where .= ' AND copies_available = 0'; }

$cnt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE $where");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$page  = min(page_number(), max(1, (int) ceil($total / PER_PAGE)));
$st = $pdo->prepare("SELECT * FROM books WHERE $where ORDER BY title LIMIT " . (int) PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($params);
$books = $st->fetchAll();

$totalTitles = (int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$totalAvail  = (int) $pdo->query('SELECT COALESCE(SUM(copies_available), 0) FROM books')->fetchColumn();
$borrowedNow = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE returned_at IS NULL')->fetchColumn();

$edit = null;
if (isset($_GET['edit'])) {
    $e = $pdo->prepare('SELECT * FROM books WHERE id = ?');
    $e->execute([(int) $_GET['edit']]);
    $edit = $e->fetch() ?: null;
}
$f = $edit ?: ['id' => 0, 'title' => '', 'author' => '', 'call_no' => '', 'category' => '', 'copies_total' => 1, 'shelf_location' => ''];

lib_header('Book Catalog & Management', 'books', true);
?>
<div class="page-title">
  <div><h1>Book Catalog &amp; Management</h1><p>Add new books to the library collection or remove obsolete copies.</p></div>
  <a class="btn-s" href="librarian_home.php">&larr; Back to Home</a>
</div>

<div class="two-col books">
  <section class="panel">
    <form class="filters" method="get">
      <input class="in grow" name="q" value="<?= h($q) ?>" placeholder="Search by title, author or call number...">
      <select class="in" name="cat" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach (BOOK_CATEGORIES as $c): ?><option<?= $c === $cat ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
      </select>
      <select class="in" name="status" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="available"<?= $status === 'available' ? ' selected' : '' ?>>Available</option>
        <option value="loan"<?= $status === 'loan' ? ' selected' : '' ?>>On Loan</option>
      </select>
      <button class="btn-p" type="submit">Search</button>
    </form>

    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Book Title &amp; Author</th><th>Accession / Call No.</th><th>Category</th><th>Copies</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$books): ?><tr><td colspan="6" class="empty">No books found.</td></tr><?php endif; ?>
        <?php foreach ($books as $b): $avail = (int) $b['copies_available'] > 0; ?>
          <tr>
            <td><strong><?= h($b['title']) ?></strong><span class="sub"><?= h($b['author']) ?></span></td>
            <td><span class="pill gray"><?= h($b['call_no']) ?></span></td>
            <td><?= h($b['category']) ?></td>
            <td><?= (int) $b['copies_available'] ?> / <?= (int) $b['copies_total'] ?></td>
            <td><span class="pill <?= $avail ? 'green' : 'blue' ?>"><?= $avail ? 'Available' : 'On Loan' ?></span></td>
            <td class="acts">
              <a class="btn-l" href="books.php?edit=<?= (int) $b['id'] ?>">Edit</a>
              <form class="inline" method="post" onsubmit="return confirm('Delete this book?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn-l del" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($total, $page) ?>

    <div class="stats three">
      <div class="stat"><div class="lbl">Total titles</div><div class="num"><?= $totalTitles ?></div></div>
      <div class="stat"><div class="lbl">Total copies available</div><div class="num green"><?= $totalAvail ?></div></div>
      <div class="stat"><div class="lbl">Currently borrowed</div><div class="num blue"><?= $borrowedNow ?></div></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= $edit ? 'Edit Book' : 'Add New Book' ?></h2><span class="pill gray">Cataloging</span></div>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <label class="f" for="f_title">Book Title *</label>
      <input class="in" id="f_title" name="title" maxlength="150" value="<?= h($f['title']) ?>" placeholder="Enter book title" required>
      <label class="f" for="f_author">Author / Publisher *</label>
      <input class="in" id="f_author" name="author" maxlength="100" value="<?= h($f['author']) ?>" placeholder="Author name" required>
      <label class="f" for="f_call">Accession / Call Number *</label>
      <input class="in" id="f_call" name="call_no" maxlength="30" value="<?= h($f['call_no']) ?>" placeholder="e.g. FIL-899.25" required>
      <label class="f" for="f_cat">Category / Section *</label>
      <select class="in" id="f_cat" name="category" required>
        <option value="">Select category...</option>
        <?php foreach (BOOK_CATEGORIES as $c): ?><option<?= $c === $f['category'] ? ' selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
      </select>
      <div class="grid2">
        <div><label class="f" for="f_copies">No. of Copies *</label><input class="in" id="f_copies" name="copies" type="number" min="1" max="999" value="<?= (int) $f['copies_total'] ?>" required></div>
        <div><label class="f" for="f_shelf">Shelf Location</label><input class="in" id="f_shelf" name="shelf" maxlength="30" value="<?= h($f['shelf_location']) ?>" placeholder="e.g. Shelf 4-B"></div>
      </div>
      <p class="mt" style="text-align:right">
        <a class="btn-s" href="books.php">Clear</a>
        <button class="btn-p" type="submit"><?= $edit ? 'Update Book' : 'Save Book' ?></button>
      </p>
    </form>
  </section>
</div>
<?php lib_footer();
