<?php /* Create / edit / delete collection modals. Expects $collReturn (page to return to). */ ?>
<div class="modal-backdrop" id="collModal" role="dialog" aria-modal="true" aria-labelledby="collModalH">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="collModalH">New collection</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="actions.php" id="collForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="collection_create" id="collAction">
        <input type="hidden" name="collection_id" value="" id="collId">
        <input type="hidden" name="return_to" value="<?= h($collReturn) ?>">
        <div class="form-grid">
          <div class="field form-full"><label for="collName">Name</label>
            <input class="input" id="collName" name="name" maxlength="100" required placeholder="e.g. Christopher Nolan"></div>
          <div class="field form-full"><label for="collDesc">Description <small>optional</small></label>
            <textarea id="collDesc" name="description" maxlength="500" placeholder="What ties these films together?"></textarea></div>
        </div>
        <button type="submit" class="btn btn-gold btn-lg btn-block modal-submit" id="collSubmit"><svg class="i"><use href="#i-check"/></svg> Create collection</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="collDeleteModal" role="alertdialog" aria-modal="true" aria-labelledby="collDelH">
  <div class="modal modal-sm">
    <form method="POST" action="actions.php" class="confirm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="collection_delete">
      <input type="hidden" name="collection_id" id="collDelId">
      <input type="hidden" name="return_to" value="collections.php">
      <div class="confirm-icon danger"><svg class="i"><use href="#i-trash"/></svg></div>
      <h3 id="collDelH">Delete this collection?</h3>
      <p id="collDelMsg">The collection is deleted. The films in it stay in your vault.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Keep it</button>
        <button type="submit" class="btn btn-danger-solid"><svg class="i"><use href="#i-trash"/></svg> Delete</button>
      </div>
    </form>
  </div>
</div>
