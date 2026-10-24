<?php
$lang['description__title'] = 'Документація адмін-панелі';
$lang['description__description'] = 'У цьому розділі наведено приклади готових рішень для оформлення адмінки. ';
$lang['description_context'] = 'Зміст';
$lang['description_title_grid'] = 'Структура сітки (grid)';
$lang['description_title_typography'] = 'Типографіка';
$lang['description_title_alerts'] = 'Сповіщення (alerts)';
$lang['description_title_buttons'] = 'Кнопки (buttons)';
$lang['description_title_tooltips'] = 'Підказки (tooltips)';
$lang['description_title_forms'] = 'Елементи форми';
$lang['description_title_switcher'] = 'Перемикач (switcher)';
$lang['description_title_tabs'] = 'Вкладки (tabs)';
$lang['description_title_add_images'] = 'Завантаження файлів і зображень';
$lang['description_title_clipboard'] = 'Запис даних у буфер обміну';
$lang['description_title_icons'] = 'Іконки';
$lang['description_info_grid'] = '<p>Структуру адмінки побудовано на bootstrap grid, яка відповідно масштабується до 12 стовпців зі збільшенням розміру пристрою або екрана перегляду. Вона містить попередньо визначені класи для спрощення компонування розмітки.</p>
                                  <p>Ця система сіток використовується для створення блоків на сторінці через групи рядків ( <strong>.row</strong> ) і стовпців ( <strong>.col-*</strong> ), у яких розміщується контент.</p>
                                  <div class="alert alert--info">
                                  <div class="alert__content">
                                  <div class="alert__title">Важливі моменти для правильного використання:</div>
                                  <ul class="list_styling">
                                      <li>Використовуйте рядки ( <strong>.row</strong> ) для створення горизонтальних груп стовпців ( <strong>.col-*</strong> ).</li>
                                      <li>Контент має бути розміщений у стовпцях ( <strong>.col-*</strong> ), і лише стовпці можуть бути безпосередніми дочірніми елементами рядків ( <strong>.row</strong> )</li>
                                      <li>Для стовпців ( <strong>.col-*</strong> ) задано внутрішні відступи ( <strong>padding</strong> ) між собою, відповідно для  рядків ( <strong>.row</strong> ) задано від\'ємні зовнішні відступи ( <strong>margin</strong> ), щоб правильно вирівняти контент у сітці</li>
                                      <li>Стовпці ( <strong>.col-*</strong> ) створюються шляхом зазначення кількості доступних 12 стовпців, які потрібно охопити. Наприклад, три рівні стовпці використовуватимуть ( <strong>.col-*-4</strong> )</li>
                                      <li>Якщо понад 12 стовпців ( <strong>.col-*</strong> ) розміщено в одному рядку ( <strong>.row</strong> ), то кожна група додаткових стовпців ( <strong>.col-*</strong> ) об\'єднуватиметься в блок і переноситиметься на новий рядок.</li>
                                      <li>Класи сітки застосовуються до пристроїв із шириною екрана, більшою або рівною розмірам контрольних точок, і перевизначають класи сітки, орієнтовані на менші пристрої. Тому, наприклад, застосування будь-якого класу ( <strong>.col-md-*</strong> ) до елемента вплине на його стиль не лише на середніх пристроях, а й на великих, якщо клас ( <strong>.col-lg-*</strong> ) відсутній.</li>
                                  </ul></div></div>';
$lang['description_title2_grid'] = 'Основні параметри сітки:';
$lang['description_info2_grid'] = '<p>Адаптивні блоки — це елементи сітки, яким встановлено один або кілька класів ( <strong>.col-*-*</strong> ). Ці блоки є основними «будівельними» цеглинками, саме вони й формують потрібну структуру.</p>
                                    <p>Ширина адаптивного блока задається у зв\'язці з типом пристрою. Це означає, що адаптивний блок на різних пристроях може мати різну ширину. Саме тому цей блок і називається адаптивним.</p>
                                    <p>Ширина, яку адаптивний блок має мати на певному пристрої, за замовчуванням задається числом від 1 до 12. Це число вказується замість другого знака <strong>*</strong> у класі ( <strong>.col-*-*</strong> ).</p>
                                    <p>Це число (за замовчуванням від 1 до 12) визначає, який відсоток від ширини батьківського елемента має мати адаптивний блок.</p>
                                    <p>Наприклад, число 1 встановлює адаптивному блоку ширину, що дорівнює 8,3% (1/12) від ширини батьківського блока. Число 12 — ширину, що дорівнює 100% (12/12) від ширини батьківського блока.</p>
                                    <p>Окрім ширини адаптивного блока, потрібно ще вказати й тип пристрою (замість першого <strong>*</strong>). Клас пристрою визначатиме, на якому viewport діятиме ця ширина.</p>
                                    <p> У нашому випадку розрізняють 5 основних класів:</p>
                                    <ul class="list_styling">
                                        <li><strong>col-xs-*</strong> (ширина viewport >0)</li>
                                        <li><strong>col-sm-*</strong> (ширина viewport >= 576px)</li>
                                        <li><strong>col-md-*</strong> (ширина viewport >= 768px)</li>
                                        <li><strong>col-lg-*</strong> (ширина viewport >= 992px)</li>
                                        <li><strong>col-xl-*</strong> (ширина області перегляду браузера >=1200px)</li>
                                    </ul>';
$lang['description_title3_grid'] = 'Приклад роботи сітки:';
$lang['description_info_typography'] = '<p>Документація та приклади типографіки, яка використовується в системі</p>';
$lang['description_title2_typography'] = 'Заголовки:';
$lang['description_title3_typography'] = 'Розмір шрифту:';
$lang['description_title4_typography'] = 'Колір шрифту:';
$lang['description_typography_class'] = 'Клас:';
$lang['description_typography_example'] = 'Приклад:';
$lang['description_typography_h_page'] = 'Заголовки для сторінок';
$lang['description_typography_h_block'] = 'Заголовки для розділів';
$lang['description_typography_h_label'] = 'Заголовки до полів (Label)';
$lang['description_promo_alerts'] = '<p>Сповіщення доступні для тексту будь-якої довжини. Модифікатори типів <strong>.alert--error</strong>, <strong>.alert--success</strong>, <strong>.alert--info</strong>, <strong>.alert--warning</strong> задають певний стиль, а модифікатор <strong>.alert--icon</strong> додає іконку до відповідного типу.</p>
                                     <p>Для правильної стилізації використовуйте один з опціональних класів.</p>';
$lang['description_alerts1'] = '<p>Це основне сповіщення, яке використовується для опису сторінок, модулів тощо</p>';
$lang['description_alerts2'] = '<p>Це сповіщення для виведення помилки, попередження про небезпеку або дуже важливої інформації</p>';
$lang['description_alerts3'] = '<p>Це сповіщення про успіх. Використовується під час збереження або інших успішних дій</p>';
$lang['description_alerts4'] = '<p>Це інфо-сповіщення, його краще використовувати для інструкцій щодо будь-яких дій</p>';
$lang['description_alerts5'] = '<p>Це сповіщення можна використовувати як для виведення поради, так і для попередження</p>';
$lang['description_promo_buttons'] = '<p>У системі є попередньо визначені стилі кнопок, кожен з яких має своє семантичне призначення та додаткові параметри для більшого контролю й гнучкості.</p>
                                      <p>Клас <strong>.btn</strong> можна використовувати як для <strong>button</strong>, так і для <strong>input</strong> та посилань. Класи <strong>.btn_blue</strong>, <strong>.btn-outline-info</strong> тощо задають певний стиль кнопкам, а класи <strong>.btn_big</strong>, <strong>.btn_small</strong> і <strong>.btn_mini</strong> змінюють розмір кнопок </p>';
$lang['description_title2_buttons'] = 'Приклад кнопок із фоном:';
$lang['description_title3_buttons'] = 'Приклад кнопок без фону:';
$lang['description_title4_buttons'] = 'Приклад великих кнопок:';
$lang['description_title5_buttons'] = 'Приклад середніх кнопок:';
$lang['description_title6_buttons'] = 'Приклад малих кнопок:';
$lang['description_btn_buttons'] = 'Кнопка';
$lang['description_info_tooltips'] = '<p>У системі реалізовано два способи виведення підказок. Перший спосіб дає змогу вивести великий текст для детального опису, а другий краще використовувати для виведення назви кнопки або іконки.</p>
                                       <p>Для прикладу розглянемо два способи використання підказок.</p>';
$lang['description_title2_tooltips'] = 'Підказки для детального опису:';
$lang['description_title3_tooltips'] = 'Підказки для виведення назви:';
$lang['description_info_switcher'] = '<p>У цьому розділі наведено приклади та рекомендації щодо використання стилів елементів керування формою і користувацьких компонентів для широкого застосування</p>';
$lang['description_title2_switcher'] = 'Перемикач (switcher)';
$lang['description_title2_switcher_label'] = 'Активність';
$lang['description_switcher_off_label'] = 'Вимкнено';
$lang['description_text_roles'] = '<ul class="mb-0"><li><b>.text_primary</b> — посилання, поточний вибір</li><li><b>.text_success</b> — успіх, активний стан; <b>.text_green</b> — те саме, лишено для сумісності</li><li><b>.text_attention</b> — стан, на який варто зважати: порада, прострочення, ціна зі знижкою</li><li><b>.text_warning</b> — попри назву, колір небезпеки: помилка, руйнівна дія</li><li><b>.text_grey</b> — другорядний текст</li></ul>';
$lang['description_buttons_roles'] = '<ul class="mb-0"><li><b>.btn_blue</b> — первинна дія сторінки: зберегти, застосувати</li><li><b>.btn-info</b> — вторинна дія тієї ж родини</li><li><b>.btn_yellow</b> — попри назву, бірюзова: пояснювальна дія (довідка, інструкція)</li><li><b>.btn-warning</b> — попри назву, фіолетова: другорядна дія — схвалити, обробити, оновити</li><li><b>.btn-success</b> — позитивна дія</li><li><b>.btn-secondary</b> — нейтральна дія</li><li><b>.btn-danger</b> — лише руйнівна дія: видалити, очистити</li></ul><p class="mt-1">Червоний — тільки для того, що руйнує або зламалось. Назви класів успадковано від стокової OkayCMS і модулі вже їх використовують, тому класи не перейменовано: обирайте клас за роллю, а не за назвою.</p>';
$lang['description_info_add_images'] = '<p>Для завантаження файлів або зображень передбачено два варіанти. Перший — це завантаження текстового файлу, а другий варіант чудово підійде для завантаження зображень і керування ними. </p>';
$lang['description_title2_add_images'] = 'Завантаження файлу:';
$lang['description_title3_add_images'] = 'Завантаження зображень:';
$lang['description_info3_add_images'] = '<p>Цей спосіб використовується для завантаження фото до банерів, товарів, категорій тощо. Можна завантажувати кілька фото одночасно, видаляти їх і переміщувати за пріоритетом. Для його налаштування потрібно підставити свої змінні в <strong>&#123;foreach&#125;</strong> і в <strong>&#123;if&#125;</strong></p>';
$lang['description_text_clip_clipboard'] = 'Натисніть, щоб скопіювати в буфер обміну.';
$lang['description_info_icons'] = '<p>Іконки інтерфейсу — це набір inline-SVG, який лежить у <strong>svg_icon.tpl</strong>. Окремої бібліотеки шрифтових іконок у панелі більше немає.</p>
                                   <p>Щоб вивести іконку, підключіть <strong>svg_icon.tpl</strong> і передайте параметр <strong>svgId</strong> — ідентифікатор потрібної іконки зі списку нижче.</p>
                                   <p>Наприклад, кошик виводиться так: <strong>&#123;include file="svg_icon.tpl" svgId="trash"&#125;</strong></p>';
$lang['description_result_icons'] = 'Результат:';
$lang['description_title2_icons'] = 'Список доступних іконок:';