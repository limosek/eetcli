
# Dokumentace souboru typu EET

Soubor typu EET slouží jako standardní formát pro eetcli EET klienta.
V každém EET souboru je jasně specifikováno, zda se jedná o účtenku, která ještě nebyla zaslána,
byla zaslána či byla zaslána s chybou. Společně s těmito informacemi pak obsahuje všechny důležité informace
jako bezpečnostní kódy a FIK pro pozdější archivaci.

Co EET soubor to účtenka. Je na uživateli, jak bude s EET soubory zacházet. Například existuje scénář,
že se EET soubor uloží a následně bude zpracováván asynchronně. Nebo se dá zajistit asociaci souboru EET
s spuštěním eetcli a vše zautomatizovat. 

## Proč EET file 
Když jsem začal psát klienta eetcli, jeho účel byl zejména v jednoduchém zaslání dat do EET, nic více.
To ale mnohdy nestačí. Potřebujete-li data uschovávat (což by tak mělo být), je mnohem lepší používat EET soubory.

## Formát souboru
EET soubor je v podstatě ini soubor. Dá se editovat jakýmkoliv textovým editorem. Toto se ale doporučuje jen 
u nových EET souborů, které mohou být vytvářeny externím programem, například účetním. EET soubory obsahující další údaje
jsou sice stále textové soubory, ale jejich modifikací se mohou stát neplatnými díky porušení bezpečnostních kodů.

### Příklad EET souboru ve stavu připraveném k odeslání (vytvořený např. externím programem)
```
[eetfile]
version=1.0
status=0
lasterror=
lasterrorcode=0
prostredi=playground

[eet]
uuid_zpravy=c1ca2e44-f374-4c24-898a-ae955ba81049
dat_odesl=
prvni_zaslani=1
overeni=1
dic_popl=CZ1212121218
id_provoz=181
id_pokl=1
porad_cis=1
dat_trzby=2017-02-20T16:39:30+01:00
celk_trzba=10
```

### Příklad EET souboru ve stavu nepovedeného odeslání (kódy jsou vymyšlené)
```
[eetfile]
version=1.0
status=1
lasterror=4
lasterrorcode=Neplatny podpis SOAP zpravy
prostredi=produkcni

[eet]
uuid_zpravy=41bd103d-f86c-441e-b116-4919a4bb2ac9
dat_odesl=2017-02-20T16:24:25+01:00
prvni_zaslani=0
dic_popl=CZ1212121218
id_provoz=181
id_pokl=1
porad_cis=1
dat_trzby=2017-02-20T16:13:00+01:00
celk_trzba=1
pkp=577fbc11c965c767cc8a8eda9a1ced56bd0bb6f279a9d63101bf6806d5d498abf77fea0beee1b08fac969cfed17454a70c151cecabb9df406b027819c7f4bbc66147466df4ec78464f7a1d35320261d203a0df329df614c9d8cb29a0a3a5201366ee89d046fae0added71987e287da84caa1e7322ffe8448f3b1a7e6fea749f0277ef7ee829009765ffe5ec7e86d8291d7955fa63523b8f00453ba0c63608f8af7f66a946077bf54cab0cc80d30bcb3ab3e69669b9e9bbdac902049f553a724399c4a4fd453fbd6ee58a2ec80d8e70a7ff6edd9101ea8ee0a5caa3987028516989a3235dfe61c14d1cdc37712dd97c1c92227ef4022a6a98b2b41fd27a59e36f
bkp=31383964343362382d38393936633730332d31663832316662612d39323939643061662d6536613264313663
```

### Příklad EET souboru úspěšně odeslaného na server (kódy jsou vymyšlené)
```
[eetfile]
version=1.0
status=1
lasterror=4
lasterrorcode=Neplatny podpis SOAP zpravy
prostredi=produkcni

[eet]
uuid_zpravy=41bd103d-f86c-441e-b116-4919a4bb2ac9
dat_odesl=2017-02-20T16:24:25+01:00
prvni_zaslani=0
dic_popl=CZ1212121218
id_provoz=181
id_pokl=1
porad_cis=1
dat_trzby=2017-02-20T16:13:00+01:00
celk_trzba=1
pkp=577fbc11c965c767cc8a8eda9a1ced56bd0bb6f279a9d63101bf6806d5d498abf77fea0beee1b08fac969cfed17454a70c151cecabb9df406b027819c7f4bbc66147466df4ec78464f7a1d35320261d203a0df329df614c9d8cb29a0a3a5201366ee89d046fae0added71987e287da84caa1e7322ffe8448f3b1a7e6fea749f0277ef7ee829009765ffe5ec7e86d8291d7955fa63523b8f00453ba0c63608f8af7f66a946077bf54cab0cc80d30bcb3ab3e69669b9e9bbdac902049f553a724399c4a4fd453fbd6ee58a2ec80d8e70a7ff6edd9101ea8ee0a5caa3987028516989a3235dfe61c14d1cdc37712dd97c1c92227ef4022a6a98b2b41fd27a59e36f
bkp=31383964343362382d38393936633730332d31663832316662612d39323939643061662d6536613264313663
fik=0b0fb7af-f508-4611-b5b0-50c9e3368b82-ff
```

