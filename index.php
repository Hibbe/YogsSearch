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
            <img width="800" height="289" src="yogsearch.webp" alt="The unofficial, unlicensed Yogsearch" class="yogart">
        </header>
        <main>
            <section class="noselect"> <!-- Search selector -->
                <form action="search.php">
                    <!--#region castmembers-->
                    <input type="checkbox" class="hiddencb" id="Lewis"      name="iid[]" value="Lewis">          <label for="Lewis"      class="castcb lewis">Lewis</label>
                    <input type="checkbox" class="hiddencb" id="Simon"      name="iid[]" value="Simon">          <label for="Simon"      class="castcb simon">Simon</label>
                    <input type="checkbox" class="hiddencb" id="Sips"       name="iid[]" value="Sips">           <label for="Sips"       class="castcb sips">Sips</label>
                    <input type="checkbox" class="hiddencb" id="Duncan"     name="iid[]" value="Duncan">         <label for="Duncan"     class="castcb duncan">Duncan</label>
                    <input type="checkbox" class="hiddencb" id="Rythian"    name="iid[]" value="Rythian">        <label for="Rythian"    class="castcb rythian">Rythian</label>
                    <input type="checkbox" class="hiddencb" id="Ben"        name="iid[]" value="Ben">            <label for="Ben"        class="castcb ben">Ben</label>
                    <input type="checkbox" class="hiddencb" id="Angor"      name="iid[]" value="Angor">          <label for="Angor"      class="castcb angor">Tom</label>
                    <input type="checkbox" class="hiddencb" id="Zylus"      name="iid[]" value="Zylus">          <label for="Zylus"      class="castcb zylus">Zylus</label>
                    <input type="checkbox" class="hiddencb" id="Zoey"       name="iid[]" value="Zoey">           <label for="Zoey"       class="castcb zoey">Zoey</label>
                    <input type="checkbox" class="hiddencb" id="Nilsey"     name="iid[]" value="Nilsey">         <label for="Nilsey"     class="castcb nilsey">Nilsey</label> <!-- 10 -->
                    <input type="checkbox" class="hiddencb" id="Ravs"       name="iid[]" value="Ravs">           <label for="Ravs"       class="castcb ravs">Ravs</label>
                    <input type="checkbox" class="hiddencb" id="Barry"      name="iid[]" value="Barry">          <label for="Barry"      class="castcb barry">Harry</label>
                    <input type="checkbox" class="hiddencb" id="Lydia"      name="iid[]" value="Lydia">          <label for="Lydia"      class="castcb lydia">Lydia</label>
                    <input type="checkbox" class="hiddencb" id="Bouphe"     name="iid[]" value="Bouphe">         <label for="Bouphe"     class="castcb bouphe">Bouphe</label>
                    <input type="checkbox" class="hiddencb" id="Osie"       name="iid[]" value="Osie">           <label for="Osie"       class="castcb osie">Osie</label>
                    <input type="checkbox" class="hiddencb" id="Briony"     name="iid[]" value="Briony">         <label for="Briony"     class="castcb briony">Briony</label>
                    <input type="checkbox" class="hiddencb" id="Kirsty"     name="iid[]" value="Kirsty">         <label for="Kirsty"     class="castcb kirsty">Kirsty</label>
                    <input type="checkbox" class="hiddencb" id="Boba"       name="iid[]" value="Boba">           <label for="Boba"       class="castcb boba">Boba</label>
                    <input type="checkbox" class="hiddencb" id="Pedguin"    name="iid[]" value="Pedguin">        <label for="Pedguin"    class="castcb pedguin">Pedguin</label>
                    <input type="checkbox" class="hiddencb" id="Trott"      name="iid[]" value="Trott">          <label for="Trott"      class="castcb trott">Trott</label> <!-- 20 -->
                    <input type="checkbox" class="hiddencb" id="Smith"      name="iid[]" value="Smith">          <label for="Smith"      class="castcb smith">Smith</label>
                    <input type="checkbox" class="hiddencb" id="Ross"       name="iid[]" value="Ross">           <label for="Ross"       class="castcb ross">Ross</label>
                    <input type="checkbox" class="hiddencb" id="Hulmes"     name="iid[]" value="Hulmes">         <label for="Hulmes"     class="castcb hulmes">Mark H</label>
                    <input type="checkbox" class="hiddencb" id="Daf"        name="iid[]" value="Daf">            <label for="Daf"        class="castcb daf">Daf</label>
                    <input type="checkbox" class="hiddencb" id="Breeh"      name="iid[]" value="Breeh">          <label for="Breeh"      class="castcb breeh">Breeh</label>
                    <input type="checkbox" class="hiddencb" id="Pyrion"     name="iid[]" value="Pyrion">         <label for="Pyrion"     class="castcb pyrion">Pyrion</label>
                    <input type="checkbox" class="hiddencb" id="Daltos"     name="iid[]" value="Daltos">         <label for="Daltos"     class="castcb daltos">Daltos</label>
                    <input type="checkbox" class="hiddencb" id="Rambler"    name="iid[]" value="Rambler">        <label for="Rambler"    class="castcb rambler">Rambler</label>
                    <input type="checkbox" class="hiddencb" id="Spiff"      name="iid[]" value="Spiff">          <label for="Spiff"      class="castcb spiff">Spiff</label>
                    <input type="checkbox" class="hiddencb" id="Martyn"     name="iid[]" value="Martyn">         <label for="Martyn"     class="castcb martyn">Martyn</label> <!-- 30 -->
                    <input type="checkbox" class="hiddencb" id="Kim"        name="iid[]" value="Kim">            <label for="Kim"        class="castcb kim">Kim</label>
                    <input type="checkbox" class="hiddencb" id="Gee"        name="iid[]" value="Gee">            <label for="Gee"        class="castcb gee">Gee</label>
                    <input type="checkbox" class="hiddencb" id="Wilsonator" name="iid[]" value="Wilsonator">     <label for="Wilsonator" class="castcb wilsonator">Wilsonator</label>
                    <input type="checkbox" class="hiddencb" id="Ryan"       name="iid[]" value="Ryan">           <label for="Ryan"       class="castcb ryan">Ryan</label>
                    <input type="checkbox" class="hiddencb" id="Vadact"     name="iid[]" value="Vadact">         <label for="Vadact"     class="castcb vadact">Vadact</label>
                    <input type="checkbox" class="hiddencb" id="Bekki"      name="iid[]" value="Bekki">          <label for="Bekki"      class="castcb bekki">Bekki</label>
                    <input type="checkbox" class="hiddencb" id="Mousie"     name="iid[]" value="Mousie">         <label for="Mousie"     class="castcb mousie">Mousie</label>
                    <input type="checkbox" class="hiddencb" id="Fionn"      name="iid[]" value="Fionn">          <label for="Fionn"      class="castcb fionn">Fionn</label>
                    <input type="checkbox" class="hiddencb" id="Mango"      name="iid[]" value="Mango">          <label for="Mango"      class="castcb mango">Mango</label>
                    <input type="checkbox" class="hiddencb" id="Shadow"     name="iid[]" value="Shadow">         <label for="Shadow"     class="castcb shadow">Shadow</label> <!-- 40 -->
                    <input type="checkbox" class="hiddencb" id="Lolip"      name="iid[]" value="Lolip">          <label for="Lolip"      class="castcb lolip">Lolip</label>
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
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/Yogsearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Report error</a>
        </footer>
    </body>
</html>
