
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

### Povinné položky
#### Contejner [eetfile]
*version* Musí být 0.1

#### Contejner [eet]


### Nepovinné položky

### Kontrolní položky

## Příklad
```

```

