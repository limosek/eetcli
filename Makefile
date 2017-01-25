
ifeq ($(P12),)
  P12=test/EET_CA1_Playground-CZ1212121218.p12
endif
ifeq ($(PASS),)
  PASS=eet
endif
ifeq ($(CRT),)
  CRT=test/EET_CA1_Playground-CZ1212121218.crt
endif
ifeq ($(KEY),)
  KEY=test/EET_CA1_Playground-CZ1212121218.pem
endif

all: key crt
clean: key-clean crt-clean

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
	php -dphar.readonly=0 buildphar.php
