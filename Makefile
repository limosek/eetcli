
ifeq ($(P12),)
  P12=keys/EET_CA1_Playground-CZ1212121218.p12
  PASS=eet
else
 ifeq ($(CRT),)
  CRT=$(shell dirname $(P12))/$(shell basename $(P12) .p12).crt
 endif
 ifeq ($(KEY),)
  KEY=$(shell dirname $(P12))/$(shell basename $(P12) .p12).pem
 endif
endif
ifneq ($(PASS),)
  PASSOPT = -passin "pass:$(PASS)"
endif
ifeq ($(CRT),)
  CRT=keys/EET_CA1_Playground-CZ1212121218.crt
endif
ifeq ($(KEY),)
  KEY=keys/EET_CA1_Playground-CZ1212121218.pem
endif

all: prepare key crt phar
clean: key-clean crt-clean phar-clean

pem: 	key-info key crt
key-info:
	@echo "p12: $(P12)"
	@echo "key: $(KEY)"
	@echo "crt: $(CRT)"

dist-clean: clean
	rm -rf vendor 

prepare: vendor/ondrejnov/eet/README.md
vendor/ondrejnov/eet/README.md:
	composer update --ignore-platform-reqs

key: 	$(KEY)
$(KEY):
	@echo "Creating $(KEY) from $(P12)"
	@openssl pkcs12 -in "$(P12)" -out "$(KEY)" -nocerts -nodes $(PASSOPT) || rm -f $(KEY)

key-clean:
	@rm -f "$(KEY)"

crt: $(CRT)
$(CRT):
	@echo "Creating $(CRT) from $(P12)"
	@openssl pkcs12 -in "$(P12)" -out "$(CRT)" -chain -nokeys -nodes $(PASSOPT) || rm -f $(CRT)
	
crt-clean:
	@rm -f "$(CRT)"
	
phar-clean:
	rm -f eetcli.phar

phar: eetcli.phar
eetcli.phar:
	php -dphar.readonly=0 vendor/bin/box build

info: eetcli.phar
	phar list -f eetcli.phar -i '\.(ini|p12|pem|crt|dist)$$'

distphar: bin/eetcli.phar
bin/eetcli.phar:
	mkdir -p bin
	php -dphar.readonly=0 vendor/bin/box build -c box-dist.json
	mv bin/eetcli.phar bin/eetcli

