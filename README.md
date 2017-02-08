
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
$ eetcli -h

Seznam dostupnych maker na vystupu v poli format:
{FIK} - fik kod
{BKP} - bkp kod
{PKP} - pkp kod

Seznam promennych prostredi, ktere je mozno pouzit:
TMP - adresar pro docasne soubory
EETCLI_DEBUG - debug level (0-4)

Options:
  -d, --debug <arg>       
  -e, --errors <arg>      
  -o, --output <arg>      
  -h, --help [<arg>]      
  --key <arg>             
  --crt <arg>             
  -n, --overovaci [<arg>] 
  -p, --neprodukcni [<arg>] 
  --uuid <arg>            
  --dic <arg>             
  --provozovna <arg>      
  --pokladna <arg>        
  --pc <arg>              
  --cas <arg>             
  --trzba <arg>           
  --format <arg>          
Pouzijte eetcli -h -d 4 pro vice informaci.
```

## Konfigurace
Všechny parametry mohou být zadány přímo přes příkazovou řádku, nicméně pokud si vytvoříte ini soubor, můžete některé věci přednastavit.
Můžete vyjít z eetcli.ini.dist. Nezapomeňte, že dokud nenakonfigurujete své údaje, *klient funguje v ověřovacím režimu* s testovacími certifikáty!
INI soubor se načítá z těchto umístění v tomto pořadí:
* z adresáře, kde je eetcli.php (dirname(eetcli.ini)/eetcli.ini)
* z domácího adresáře uživatele  HOME/eetcli.ini, HOME/.eetclirc
* z globálního config adresáře (/etc/eetcli.ini)
* z distribučního phar archivu (eetcli.ini.dist)
```
[global]
; Pro vyssi uroven ladicich informaci
;debug=4

; Kam zapsat vystup. Pokud neni nastaveno, pak stdout
;output=fik.txt
; Kam zapsat chyby. Pokud neni nastaveno, pak stderr
;errors=eet.log

[eet]
; Vychozi stav v distribucnim balicku je overovaci. Odkomentujte pro ostry
overovaci=1
; Vychozi stav v distribucnim balicku je neprodukcni prostredi. Odkomentujte pro ostry
neprodukcni=1
; Format, ve kterem se vypisi data. Je mozno pouzit \n,\r a jine znacky. Makra budou zamenena za kody. 
;format=fik={FIK}\nbkp={BKP}\npkp={PKP}\n

[cert]
; Vychozi distribucni certifikaty. Zmente za svoje
crt=./keys/EET_CA1_Playground-CZ1212121218.crt
key=./keys/EET_CA1_Playground-CZ1212121218.pem

[firma]
; Vychozi informace pro testovaci certifikat. Zamente za vase data
dic=CZ1212121218
pokladna=1
provozovna=181
```

## Příklady
Odešli tržbu 500,-Kč v ověřovacím režimu, použij klíč abcd.pem a certifikat abcd.crt. Použij pořadové číslo 1, pokladnu 1 a provozovnu 11.
```
eetcli --crt abcd.crt --key abcd.pem --pc 1 --pokladna 1 --provozovna 11 --trzba 500 -n
```
nebo v ostrém režimu
```
eetcli --crt abcd.crt --key abcd.pem --pc 1 --pokladna 1 --provozovna 11 --trzba 500
```

# Instalace

Teoreticky by mělo stačit stáhnout PHP a pak spouštět přímo eetcli. Návod pro instalaci pro jednotlivé systémy nebudu psát, kdo chce tento SW používat, jistě si to najde:)
Případně mi pošlete info a já můžu návod upravit.
Instalace na debian a podobných systémech:

```
sudo apt-get update
sudo apt-get install php-cli php5-curl
wget https://raw.githubusercontent.com/limosek/eetcli/0.2/bin/eetcli
chmod +x eetcli
./eetcli -h
```
Pokud chcete, můžete klienta přidat i do spustitelné cesty, takže bude zavolatelný z jakéhokoliv místa. 

Certifikáty pro EET jsou distribuovány jako .p12 soubory. Tento klient vyžaduje .pem a .crt soubory, které je potřeba extrahovar z .p12.
Pokud máte nainstalovaný program make a openssl, můžete si certifikát převést takto (pouze pokud si stáhnete git repozitář):
```
git clone https://github.com/limosek/eetcli.git
cd eetcli
# Zkopirujte svuj .p12 klic do adresare keys/ a nasledne
make pem P12=keys/muj_klic.p12 PASS=heslo_ke_klici 
```
V adresáři keys pak vzniknou nové soubory .pem a .crt, které můžete použít a odkázat se na ně například z ini souboru.

# Vývoj a kustomizace phar

Pokud chcete pomoci s vývojem, určitě neodmítnu :) 
Teoreticky si můžete vytvořit svůj vlastní phar archív a uložit do něj své klíče i ini soubor.
Stačí na to pustit make a vytvoří se eetcli.phar který je modifikován pro
vaše použití. *Součástí takového balíku jsou pak všechny klíče* z adresáře
keys tak  *eetcli.ini*. S tím jsou zase samozřejmě spojeny bezpečnostní věci, tedy že byste pak neměli phar archív nikdy dát z ruky, ale to už je mimo rámec tohoto dokumentu.
Pokud chcete vytvořit vývojové prostředí, potřebujete mít k dispozici php, phar, composer a make. Pro vytvoření phar archivu můžete použít:
```
git clone git@github.com:limosek/eetcli.git
cd eetcli
make clean
make
```

Pokud chcete změnit parametry pharu, můžete použít například:
```
make P12=cesta_ke_klici.p12 PASS=heslo_ke_klici 
```
Nezapomeňte, že součástí vytvořeného archivu jsou  *hesla, klíče ale i ini soubory*!

Pro vyčištění do původního stavu použijte
```
make distclean
```

Pro vytvoření čistého eetcli (v adresáři bin) pro účely další distribuce (bez klíčů a
osobních informací), použijte 
```
make distphar
```
