
ifeq ($(P12),)
  P12=keys/EET_CA1_Playground-CZ1212121218.p12
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
clean: key-clean crt-clean

prepare: vendor/ondrejnov/eet/README.md
vendor/ondrejnov/eet/README.md:
	composer update
	# Workaround - one file is missing in library
	cd vendor/ondrejnov/eet/Schema && wget -c https://raw.githubusercontent.com/ondrejnov/eet/master/src/Schema/ProductionService.wsdl

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

phar:
	rm -f eetcli.phar
	php -dphar.readonly=0 vendor/bin/box build

