<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | Find new videos featuring your favourite creators</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <link rel="preload" href="/yogsearch.webp" as="image">
        <link rel="preload" href="/yogthumbs.webp" as="image">
        <link rel="stylesheet" href="index.css">
        <!-- Favico region -->
        <link rel="icon" type="image/png" href="/favico/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favico/favicon.svg" />
        <link rel="shortcut icon" href="/favico/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/favico/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="Yogsearch" />
        <link rel="manifest" href="/favico/site.webmanifest" />
    </head>
    <body>
        <header> 
            <img width="800" height="289" src="yogsearch.webp" alt="Yogsearch logo" class="yogart">
        </header>
        <main>
            <section class="noselect"> <!-- Search selector -->
                <form action="search.php">
                    <!--#region castmembers-->
                    <input type="checkbox" class="hiddencb" id="Lewis"      name="iid[]" value="Lewis">          <label for="Lewis"      class="castcb Lewis">Lewis</label>
                    <input type="checkbox" class="hiddencb" id="Simon"      name="iid[]" value="Simon">          <label for="Simon"      class="castcb Simon">Simon</label>
                    <input type="checkbox" class="hiddencb" id="Sips"       name="iid[]" value="Sips">           <label for="Sips"       class="castcb Sips">Sips</label>
                    <input type="checkbox" class="hiddencb" id="Duncan"     name="iid[]" value="Duncan">         <label for="Duncan"     class="castcb Duncan">Duncan</label>
                    <input type="checkbox" class="hiddencb" id="Rythian"    name="iid[]" value="Rythian">        <label for="Rythian"    class="castcb Rythian">Rythian</label>
                    <input type="checkbox" class="hiddencb" id="Ben"        name="iid[]" value="Ben">            <label for="Ben"        class="castcb Ben">Ben</label>
                    <input type="checkbox" class="hiddencb" id="Angor"      name="iid[]" value="Angor">          <label for="Angor"      class="castcb Tom">Tom</label>
                    <input type="checkbox" class="hiddencb" id="Zylus"      name="iid[]" value="Zylus">          <label for="Zylus"      class="castcb Zylus">Zylus</label>
                    <input type="checkbox" class="hiddencb" id="Zoey"       name="iid[]" value="Zoey">           <label for="Zoey"       class="castcb Zoey">Zoey</label>
                    <input type="checkbox" class="hiddencb" id="Nilesy"     name="iid[]" value="Nilesy">         <label for="Nilesy"     class="castcb Nilesy">Nilesy</label> <!-- 10 -->
                    <input type="checkbox" class="hiddencb" id="Ravs"       name="iid[]" value="Ravs">           <label for="Ravs"       class="castcb Ravs">Ravs</label>
                    <input type="checkbox" class="hiddencb" id="Barry"      name="iid[]" value="Barry">          <label for="Barry"      class="castcb Harry">Harry</label>
                    <input type="checkbox" class="hiddencb" id="Lydia"      name="iid[]" value="Lydia">          <label for="Lydia"      class="castcb Lydia">Lydia</label>
                    <input type="checkbox" class="hiddencb" id="Bouphe"     name="iid[]" value="Bouphe">         <label for="Bouphe"     class="castcb Bouphe">Bouphe</label>
                    <input type="checkbox" class="hiddencb" id="Osie"       name="iid[]" value="Osie">           <label for="Osie"       class="castcb Osie">Osie</label>
                    <input type="checkbox" class="hiddencb" id="Briony"     name="iid[]" value="Briony">         <label for="Briony"     class="castcb Briony">Briony</label>
                    <input type="checkbox" class="hiddencb" id="Kirsty"     name="iid[]" value="Kirsty">         <label for="Kirsty"     class="castcb Kirsty">Kirsty</label>
                    <input type="checkbox" class="hiddencb" id="Boba"       name="iid[]" value="Boba">           <label for="Boba"       class="castcb Boba">Boba</label>
                    <input type="checkbox" class="hiddencb" id="Pedguin"    name="iid[]" value="Pedguin">        <label for="Pedguin"    class="castcb Pedguin">Pedguin</label>
                    <input type="checkbox" class="hiddencb" id="Trott"      name="iid[]" value="Trott">          <label for="Trott"      class="castcb Trott">Trott</label> <!-- 20 -->
                    <input type="checkbox" class="hiddencb" id="Smith"      name="iid[]" value="Smith">          <label for="Smith"      class="castcb Smith">Smith</label>
                    <input type="checkbox" class="hiddencb" id="Ross"       name="iid[]" value="Ross">           <label for="Ross"       class="castcb Ross">Ross</label>
                    <input type="checkbox" class="hiddencb" id="Hulmes"     name="iid[]" value="Hulmes">         <label for="Hulmes"     class="castcb Hulmes">Mark H</label>
                    <input type="checkbox" class="hiddencb" id="Daf"        name="iid[]" value="Daf">            <label for="Daf"        class="castcb Daf">Daf</label>
                    <input type="checkbox" class="hiddencb" id="Breeh"      name="iid[]" value="Breeh">          <label for="Breeh"      class="castcb Breeh">Breeh</label>
                    <input type="checkbox" class="hiddencb" id="Pyrion"     name="iid[]" value="Pyrion">         <label for="Pyrion"     class="castcb Pyrion">Pyrion</label>
                    <input type="checkbox" class="hiddencb" id="Daltos"     name="iid[]" value="Daltos">         <label for="Daltos"     class="castcb Daltos">Daltos</label>
                    <input type="checkbox" class="hiddencb" id="Rambler"    name="iid[]" value="Rambler">        <label for="Rambler"    class="castcb Rambler">Rambler</label>
                    <input type="checkbox" class="hiddencb" id="Spiff"      name="iid[]" value="Spiff">          <label for="Spiff"      class="castcb Spiff">Spiff</label>
                    <input type="checkbox" class="hiddencb" id="Martyn"     name="iid[]" value="Martyn">         <label for="Martyn"     class="castcb Martyn">Martyn</label> <!-- 30 -->
                    <input type="checkbox" class="hiddencb" id="Kim"        name="iid[]" value="Kim">            <label for="Kim"        class="castcb Kim">Kim</label>
                    <input type="checkbox" class="hiddencb" id="Gee"        name="iid[]" value="Gee">            <label for="Gee"        class="castcb Gee">Gee</label>
                    <input type="checkbox" class="hiddencb" id="Wilsonator" name="iid[]" value="Wilsonator">     <label for="Wilsonator" class="castcb Wilsonator">Wilsonator</label>
                    <input type="checkbox" class="hiddencb" id="Ryan"       name="iid[]" value="Ryan">           <label for="Ryan"       class="castcb Ryan">Ryan</label>
                    <input type="checkbox" class="hiddencb" id="Vadact"     name="iid[]" value="Vadact">         <label for="Vadact"     class="castcb Vadact">Vadact</label>
                    <input type="checkbox" class="hiddencb" id="Bekki"      name="iid[]" value="Bekki">          <label for="Bekki"      class="castcb Bekki">Bekki</label>
                    <input type="checkbox" class="hiddencb" id="Mousie"     name="iid[]" value="Mousie">         <label for="Mousie"     class="castcb Mousie">Mousie</label>
                    <input type="checkbox" class="hiddencb" id="Fionn"      name="iid[]" value="Fionn">          <label for="Fionn"      class="castcb Fionn">Fionn</label>
                    <input type="checkbox" class="hiddencb" id="Mango"      name="iid[]" value="Mango">          <label for="Mango"      class="castcb Mango">Mango</label>
                    <input type="checkbox" class="hiddencb" id="Shadow"     name="iid[]" value="Shadow">         <label for="Shadow"     class="castcb Shadow">Shadow</label> <!-- 40 -->
                    <input type="checkbox" class="hiddencb" id="Lolip"      name="iid[]" value="Lolip">          <label for="Lolip"      class="castcb Lolip">Lolip</label>
                    <!--#endregion-->
                <p>
                    <!-- <input type="checkbox" id="ExclusiveCast" name="ExclusiveCast">
                    <label for="ExclusiveCast">Exclusive</label> -->
                    
                </p>
                <p> <!-- Here goes the search button -->
                    <input type="submit" id="Searchsubmit" value="Search" />
                </p>
                </form>
            </section>

        </main>
        <footer>
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/YogsSearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Contribute</a>
            <p class="fodisc">Yogsearch is a <strong>fanpage</strong> and is <strong>not associated with or endorsed</strong> by the Yogscast</p>
        </footer>
    </body>
</html>
