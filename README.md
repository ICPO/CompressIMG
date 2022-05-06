Плагин по конвертации изображений в `WEBP` формат для Yii2

1) Поместить плагин `CompressIMG.php`, например, в models 
2) Переместить содержимое папки web в соответствующую папку проекта. 

__ВАЖНО!__
По умолчанию плагин использует дирректорию `assets/thumbnail` для создания кешированных изображений. Указать свою можно либо внеся правки непосредственно в константу `PATH_TO_THUMBNAIL`, либо в конфиг `web.php`. Пример:
```php

'thumbnail' => [
  'cacheAlias' => 'assets/thumbnails',
],

```

Должно получиться так: `web/images/CompressIMG`. 
Папка содержит вспомогательные картинки на тот случай, если где-то возникнет ошибка, либо, например, изображение не будет найдено по переданному пути. 


## Пример инициализации и конвертации 

```php

$image = (new \app\models\CompressIMG())->cache_as_webp(
  '@webroot'.$slide['background'], 
  50, 
  [1920,1080] 
); 

```

## Разбор в подробностях
1) `$image` – содержит ссылку на кешированное изображение 
2) `'@webroot'.$slide['background']` – Путь до изображения. Алиас `@webroot` __обязателен__. 
3) `50` – новое качество конвертируемого изображения. Можно написать `false`, в таком случае по умолчанию будет использоваться константа `IMG_QUALITY` плагина. Уникального процента качества нет. Золотая середина __40-60__. В целом, все зависит от ситуации. 
Например, миниатюрам нет смысла давать 100 качество, если при клике на неё открывается полное изображение максимального качества (как, например, fancybox плагин).  

4) `[1920,1080]` – новая ширина и высота конвертируемого изображения. 
Можно написать `false`, в таком случае по умолчанию будет использоваться исходная ширина и высота изображения. 

___Не рекомендуется___ писать следующим образом:

`[1920]`, `[1920,]`, `[,1080]` 

Это может вызвать ошибку или привести к непредсказуемым последствиям конвертации. Например, неправильные пропорции. 

## Как правильно рассчитать пропорции

Чтобы идеально подобрать пропорции высоты и ширины – не прокатит посмотреть ширину и высоту области, куда будет подгружаться изображение через инспектор кода в браузере. Хорошим решением будет, например, воспользоваться сайтом - https://ciox.ru/aspect-ratio.

1) В исходную ширину вставляем исходную ширину, например, `1280`. 
2) В исходную высоту вставляем исходную высоту, например, `720`. 
3) В новый размер пишем желаемые циферки ширины/высоты, например, новый размер `500 по ширине` 

Жмем выполнить и получаем корректные значения, которые нужно вписать в параметр плагина `[ширина,высота]`. 

![Alt-текст](https://i.ibb.co/T2LqJDw/Opera-2022-05-06-154706-onedrive-live-com.png "Пример")

Можно вбивать любые значения в плагин и он сделает так, как вы захотите. Но если нужны четкие пропорции – сайт выше топ вариант. 

## Как работает кеширование 

Кеширование работает в автоматическом режиме.  
1) Первично - кешируются все объекты (изображения), на которые передана ссылка. 
2) При повторном обращении к странице (когда уже существуют кешированные объекты), происходит проверка на дату создания кеша и проверка ресайзинга. Если есть расхождения или файл кеша устарел - будет произведено обновление. 

 
## Известные недочеты
1) Нет проверки кеша по качеству, если ресайзинга не было и дата кеша валидная
