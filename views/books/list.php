<h3><?= static::$title ?></h3>
<table class="table table-striped table-hover">
    <tr>
        <th>ID</th>
        <th>Isbn</th>
        <th>Ean</th>
        <th>Name</th>
        <th>Description</th>
        <th>Netto</th>
        <th>Brutto</th>
        <th>Language</th>
        <th>Series</th>
        <th>Code</th>
    </tr>
    <?php
    foreach ($books as $book):?>
        <tr>
            <td><?= $book['id'] ?></td>
            <td><?= $book['isbn'] ?></td>
            <td><?= $book['ean'] ?></td>
            <td><?= $book['name'] ?></td>
            <td><?= $book['description'] ?></td>
            <td><?= $book['netto'] ?></td>
            <td><?= $book['brutto'] ?></td>
            <td><?= $book['language'] ?></td>
            <td><?= $book['series'] ?></td>
            <td><?= $book['code'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>
