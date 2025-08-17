<?php /* public/index.php */ ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Мини-CRM</title>
  <link rel="stylesheet" href="./assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">Мини-CRM</div>
    <nav class="actions">
      <button id="addBtn" class="btn primary">Добавить</button>
      <button id="editBtn" class="btn">Редактировать</button>
      <button id="deleteBtn" class="btn danger">Удалить</button>
      <span class="spacer"></span>
      <select id="entitySwitcher" class="select">
        <option value="deals">Сделки</option>
        <option value="contacts">Контакты</option>
      </select>
    </nav>
  </header>

  <main class="layout">
    <aside class="menu" id="menu">
      <ul>
        <li data-entity="deals"  class="active">Сделки</li>
        <li data-entity="contacts">Контакты</li>
      </ul>
    </aside>

    <section class="list" id="list"></section>

    <section class="content" id="content">
      <div class="placeholder">Выберите элемент из списка</div>
    </section>
  </main>

  <div id="modal" class="modal hidden" aria-hidden="true"></div>

  <script src="./assets/js/app.js"></script>
</body>
</html>
