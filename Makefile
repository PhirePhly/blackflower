VER:=$(shell grep OC_VERSION VERSION | cut -d \" -f 2)
PATCH:=$(shell grep OC_LEVEL VERSION | cut -d \" -f 2)
NAME=cad-$(VER)$(PATCH)
TMP=../tmp/$(NAME)

release: tag package

package:
	mkdir -p $(TMP)
	cd ../tmp; rm -rf $(NAME)
	svn export --quiet --force --non-interactive . $(TMP)
	cd ../tmp; tar -zcf $(NAME).tar.gz $(NAME)

tag:
	svn copy https://secure.forlorn.net/svn/cad/trunk \
                 https://secure.forlorn.net/svn/cad/tags/$(VER)$(PATCH)

