<div class="container">

  <div class="pagecontent">

    <div class="menu">
      <a href="/?scenario=kitting" class="menubtn">Kitting</a>
      <a href="/?scenario=storage" class="menubtn">Mannloses Lager</a>
      <?php if ($role_id==LTLS_ADMIN) { ?><a href="/?scenario=init" class="menubtn">Initialisierung (Nur Administrator)</a><?php } ?>
    </div>

  </div>
</div>
