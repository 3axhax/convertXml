<h3><?= static::$title ?></h3>
<form action="/index" method="post" class="form-horizontal" enctype="multipart/form-data">
    <div class="form-group">
        <label class="col-lg-2 control-label">XML файл:</label>
        <div class="col-lg-3">
            <input type="hidden" name="MAX_FILE_SIZE" value="300000" />
            <input type="file" class="form-control" name="importfile">
        </div>
    </div>
    <div class="col-lg-offset-2">
        <button type="submit" class="btn btn-success" name="submitbutton">Загрузить <span class="glyphicon glyphicon-import"></button>
    </div>
</form>

<?php if (($ans !== true) && ($ans)) :?>
    <?php $status = \components\Report::instance()->getCountError()?>
    <br>
    <div class="alert alert-<?= ($status != 0) ? 'danger' : 'success'?> alert-dismissible col-lg-offset-2 col-lg-4" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <?=$ans?>
    </div>
<?php endif;?>
<div class="col-lg-offset-1 col-lg-10">
<pre>
    <?php print_r($res)?>
</pre>
</div>
