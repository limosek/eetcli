
# EETCLI - Commandline klient pro EET (etrzby.cz)

Tento klient slouží pro odesílání tržeb pomocí skriptu nebo externího programu. Základním zadáním bylo udělat co nejjednodušší implementaci. 
Je mi jasné, že čisté "C" by asi bylo mnohem čistější, ale PHP bylo jednodušší. 
Snažil jsem se o maximální multiplatformnost. Vzhledem k použití phar je jediná závislost na PHP, které je dostupné skoro na každém systému.
Klient sám o sobě neřeší žádnou evicenci dokladů a neřeší ani generování pořadových čísel dokladů. Pouze odesílá tržby na základě jasných požadavků.
V případě, že dojde k chybě, vrátí návratový kód. V opačném případě vrací FIK na standardním výstupu.
Klient by měl běžet i na relativně slabých systémech jako je raspberry PI. Může být spouštěn i jako externí program například z účetního software při vytvoření paragonu.

# Licence

Tento projekt je licencován pod GPL3. Zříkám se jakékoliv zodpovědnosti při používání tohoto programu.
I když se snažím vše odzkoušet, používání pro odesílání datových zpráv do EET je jen na Vás a Vaší zodpovědnosti.
Použil jsem komponenty třetích stran, které jsou rovněž šířeny pod otevřenou licencí, zejména
* [ondrejnov/eet](https://github.com/ondrejnov/eet)
* dealnews/console
* kherge/box

# Použití
Malý návod k použití je i součástí samotného příkazu.
```
./eetcli.phar -h
This is commandline interface for Czech EET (etrzby.cz)
USAGE:
  eetcli.phar  -h [--cas dat_trzby] [--crt crt] [--dic dic] [--key key] [--keysecret secret] [-n] [--output soubor] [--p12 p12] [--pc porad_cis] [--pokladna id_pokl] [--provozovna id_provoz] [-q] [--timeout mS] [--trzba celk_trzba] [--uuid uuid] [-v]

OPTIONS:
  --cas         dat_trzby   Datum a cas trzby
  --crt         crt         Certificate public key (pem format)
  --dic         dic         DIC
   -h                       Shows this help
  --key         key         Certificate private key (pem format)
  --keysecret   secret      Private key password (can be set by env
                            EET_KEYSECRET too)
   -n                       Overovaci rezim
  --output      soubor      Zapsat fik do souboru 
  --p12         p12         Certificate in PKCS12 format
  --pc          porad_cis   Poradove cislo
  --pokladna    id_pokl     ID pokladny
  --provozovna  id_provoz   ID provozovny
   -q                       Be quiet. Will override -v
  --timeout     mS          Timeout v milisekundach
  --trzba       celk_trzba  Celkova trzba v Kc
  --uuid        uuid        UUID
   -v                       Be verbose. Additional v will increase verbosity.
                            e.g. -vvv

Copyright Lukas Macura  2017-2017
```

## Konfigurace
Všechny parametry mohou být zadány přímo přes příkazovou řádku, nicméně pokud si vytvoříte ini soubor, můžete některé věci přednastavit.
Můžete vyjít z eetcli.ini.dist. Zkopírujte ho do složky eetcli a upravte pro Vaše poižití.
```
[global]
;verbose=1
overovaci=1

[cert]
crt=./keys/EET_CA1_Playground-CZ1212121218.crt
key=./keys/EET_CA1_Playground-CZ1212121218.pem
;secret=1234

[firma]
dic=CZ1212121218
pokladna=1
provozovna=181
```

## Příklady
Odešli tržbu 500,-Kč v ověřovacím režimu, použij klíč abcd.p12 s heslem bcdef. Použij pořadové číslo 1, pokladnu 1 a provozovnu 11.
```
eetcli.phar --p12 abcd.p12 --pc 1 --pokladna 1 --provozovna 11 --trzba 500 -n
```
nebo v ostrém režimu
```
eetcli.phar --p12 abcd.p12 --pc 1 --pokladna 1 --provozovna 11 --trzba 500
```

# Instalace

Teoreticky by mělo stačit stáhnout PHP a pak spouštět přímo eetcli.phar. Návod pro instalaci pro jednotlivé systémy nebudu psát, kdo chce tento SW používat, jistě si to najde:)
Případně mi pošlete info a já můžu návod upravit.
Instalace na debian a podobných systémech:

```
sudo apt-get update
sudo apt-get install php-cli
wget https://raw.githubusercontent.com/limosek/eetcli/0.1/eetcli.phar
chmod +x eetcli.phar
./eetcli.phar
```
Pokud chcete, můžete klienta přidat i do spustitelné cesty, takže bude zavolatelný z jakéhokoliv místa. 

# Vývoj a kustomizace phar

Pokud chcete pomoci s vývojem, určitě neodmítnu :) 
Teoreticky si můžete vytvořit svůj vlastní phar archív a uložit do něj své klíče i ini soubor.
S tím jsou zase samozřejmě spojeny bezpečnostní věci, tedy že byste pak neměli phar archív nikdy dát z ruky, ale to už je mimo rámec tohoto dokumentu.
Pokud chcete vytvořit vývojové prostředí, potřebujete mít k dispozici php, phar, composer a make. Pro vytvoření phar archivu můžete použít:
```
git clone git@github.com:limosek/eetcli.git
cd eetcli
make clean
make
```

Pokud chcete změnit parametry pharu, můžete použít 
```
make P12=cesta_ke_klici.p12 PASS=heslo_ke_klici 
```
Nezapomeňte, že součástí vytvořeného archivu jsou  *hesla, klíče ale i ini soubory*!

Pro vyčištění do původního stavu použijte
```
make distclean
```



