
ifeq ($(P12),)
  P12=keys/EET_CA1_Playground-CZ1212121218.p12
 ifeq ($(CRT),)
  CRT=$(shell basename $(P12) .p12).crt
 endif
 ifeq ($(KEY),)
  KEY=$(shell basename $(P12) .p12).pem
 endif
endif
ifeq ($(PASS),)
  PASS=eet
endif
ifeq ($(CRT),)
  CRT=keys/EET_CA1_Playground-CZ1212121218.crt
endif
ifeq ($(KEY),)
  KEY=keys/EET_CA1_Playground-CZ1212121218.pem
endif

all: prepare key crt phar
clean: key-clean crt-clean phar-clean
	
dist-clean: clean
	rm -rf vendor 

prepare: vendor/ondrejnov/eet/README.md
vendor/ondrejnov/eet/README.md:
	composer update
	# Workaround - one file is missing in library
	cd vendor/ondrejnov/eet/src/Schema && wget -c https://raw.githubusercontent.com/ondrejnov/eet/master/src/Schema/ProductionService.wsdl

key: $(KEY)
$(KEY):
	@openssl pkcs12 -in "$(P12)" -out "$(KEY)" -nocerts -nodes -passin "pass:$(PASS)"

key-clean:
	@rm -f "$(KEY)"

crt: $(CRT)
$(CRT):
	@openssl pkcs12 -in "$(P12)" -out "$(CRT)" -nokeys -nodes -passin "pass:$(PASS)"
	
crt-clean:
	@rm -f "$(CRT)"
	
phar-clean:
	rm -f eetcli.phar

phar: eetcli.phar
eetcli.phar:
	php -dphar.readonly=0 vendor/bin/box build

info: eetcli.phar
	phar list -f eetcli.phar -i '\.(ini|p12|pem|crt|dist)$$'

a:
