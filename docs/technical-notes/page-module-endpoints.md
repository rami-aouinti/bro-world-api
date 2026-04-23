# Page module endpoints

Liste extraite automatiquement des attributs `#[Route(...)]` du module `src/Page/Transport/Controller/Api`.
Chemin HTTP final exposé par l’API : `/api` + `Path`.

| Method(s) | Path | Controller file |
|---|---|---|
| `-` | `/v1/page/about` | `src/Page/Transport/Controller/Api/V1/About/AboutController.php` |
| `-` | `/v1/page/contact` | `src/Page/Transport/Controller/Api/V1/Contact/ContactController.php` |
| `-` | `/v1/page/faq` | `src/Page/Transport/Controller/Api/V1/Faq/FaqController.php` |
| `-` | `/v1/page/home` | `src/Page/Transport/Controller/Api/V1/Home/HomeController.php` |
| `GET` | `/v1/page/public/about/{languageCode}` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
| `GET` | `/v1/page/public/contact/{languageCode}` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
| `GET` | `/v1/page/public/faq/{languageCode}` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
| `GET` | `/v1/page/public/home/{languageCode}` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
| `GET` | `/v1/page/public/{pageSlug}/{languageCode}` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
| `POST` | `/v1/page/public/contact` | `src/Page/Transport/Controller/Api/V1/Public/PublicPageController.php` |
