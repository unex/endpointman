# endpointman: development tasks
# Requires docker and docker compose. See docker/README.md

DC       := docker compose -f docker/compose.yml
DC_LIVE  := docker compose -f docker/compose.yml -f docker/compose.live.yml
TOOLS    := $(DC) --profile tools run --rm
DBPASS   := freepbx
RAWNAME  := endpointman
VERSION  := $(shell sed -n 's/.*<version>\([^<]*\)<\/version>.*/\1/p' module.xml | head -1)
TARBALL  := build/$(RAWNAME)-$(VERSION).tgz
RELEASE_TARBALL := build/$(RAWNAME)-$(VERSION)-release.tgz
MODDIR   := /var/www/html/admin/modules

.DEFAULT_GOAL := help

.PHONY: help up up-live down destroy logs shell install package package-verify \
        package-release deploy module-install module-reload lint compat \
        compat-deps stan audit test codemirror i18n fix-perms

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ---- stack ----------------------------------------------------------------

up: ## Start stack in PACKAGE mode (no bind mount) — http://localhost:8080
	$(DC) up -d

up-live: ## Start stack in LIVE mode (repo bind-mounted; see docker/README.md)
	$(DC_LIVE) up -d

down: ## Stop containers, keep data
	$(DC) down

destroy: ## Stop and delete all volumes (full reset)
	$(DC) down -v

logs: ## Tail FreePBX logs
	$(DC) logs -f freepbx

shell: ## Shell inside the FreePBX container
	$(DC) exec freepbx bash

# The FreePBX installer prints "You have successfully installed FreePBX" and
# then exits 1. Thus ignore its status, and check that it wrote
# /etc/freepbx.conf.
install: ## One-time: run the FreePBX installer (after `make up`)
	-$(DC) exec -w /usr/local/src/freepbx freepbx \
		php install -n --dbuser=freepbxuser --dbpass=$(DBPASS) --dbhost=db
	$(DC) exec freepbx test -f /etc/freepbx.conf
	$(DC) exec freepbx fwconsole setting DEVEL 1

## ---- packaging -------------------------------------------------------------

package: ## Build build/endpointman-<version>.tgz from the working tree
	@rm -rf build/stage && mkdir -p build/stage/$(RAWNAME)
	@rsync -a --exclude-from=docker/package-exclude.txt ./ build/stage/$(RAWNAME)/
	@tar czf $(TARBALL) -C build/stage $(RAWNAME)
	@rm -rf build/stage
	@echo "built $(TARBALL) ($$(du -h $(TARBALL) | cut -f1), $$(tar tzf $(TARBALL) | wc -l) entries)"

package-verify: package ## Build, then list what would ship
	@tar tzf $(TARBALL) | sort

# `package` copies the working tree, which is correct during development:
# `make deploy` then ships the edit that you did not commit yet. Do not build
# a release that way. It ships all files that are in the tree, and you cannot
# build it again from a tag. This target takes only committed files.
#
# The two targets must agree on WHICH files ship. `package` uses
# docker/package-exclude.txt and this target uses .gitattributes. The two
# lists can drift, so the diff below compares the results. If it fails, one
# list changed and the other did not.
package-release: ## Build the release tarball from the last commit, not the working tree
	@test -z "$$(git status --porcelain)" || \
		{ echo "REFUSING: working tree is dirty; commit or stash first." >&2; exit 1; }
	@$(MAKE) --no-print-directory package
	@mkdir -p build
	@git archive --format=tar.gz --prefix=$(RAWNAME)/ -o $(RELEASE_TARBALL) HEAD
	@tar tzf $(TARBALL)         | grep -v '/$$' | sort > build/.files-tree
	@tar tzf $(RELEASE_TARBALL) | grep -v '/$$' | sort > build/.files-commit
	@diff build/.files-tree build/.files-commit \
		|| { echo "REFUSING: docker/package-exclude.txt and .gitattributes disagree (< tree, > commit)." >&2; \
		     rm -f $(RELEASE_TARBALL) build/.files-tree build/.files-commit; exit 1; }
	@rm -f build/.files-tree build/.files-commit
	@echo "built $(RELEASE_TARBALL) from $$(git rev-parse --short HEAD)" \
	      "($$(du -h $(RELEASE_TARBALL) | cut -f1), $$(tar tzf $(RELEASE_TARBALL) | wc -l) entries)"

# tar does not remove files that the new tarball does not contain. An
# extract over the previous deploy thus keeps deleted files, and a test of a
# deletion is not valid. Clear the directory first. Refuse when it is the
# live-mode bind mount, which is the host working tree.
deploy: package ## Build the tarball and install it via FreePBX's own installer
	$(DC) cp $(TARBALL) freepbx:/tmp/$(RAWNAME).tgz
	$(DC) exec freepbx sh -c 'if mountpoint -q $(MODDIR)/$(RAWNAME); then \
		echo "REFUSING: $(MODDIR)/$(RAWNAME) is a bind mount (live mode)." >&2; \
		echo "Run make down && make up before make deploy." >&2; exit 1; fi; \
		rm -rf $(MODDIR)/$(RAWNAME)'
	$(DC) exec freepbx tar xzf /tmp/$(RAWNAME).tgz -C $(MODDIR)
	$(DC) exec freepbx fwconsole ma install $(RAWNAME)
	$(DC) exec freepbx fwconsole reload

module-install: ## Install/enable endpointman already present in the modules dir
	$(DC) exec freepbx fwconsole ma install $(RAWNAME)
	$(DC) exec freepbx fwconsole reload

module-reload: ## Re-read module state without reinstalling
	$(DC) exec freepbx fwconsole reload

## ---- static analysis (no FreePBX needed) -----------------------------------

lint: ## php -l every PHP file
	@$(TOOLS) --entrypoint sh php -c \
		'find . -name "*.php" -not -path "./vendor/*" \
		 -not -path "./build/*" -print0 | xargs -0 -n1 php -l | grep -v "^No syntax errors" || true'

# The qa image has no PHPCompatibility standard, and the last stable
# PHPCompatibility (9.3.5) has no PHP 8.x sniffs. Thus install
# PHPCompatibility 10.x-dev and phpcs >= 4.0.2 into docker/compat/vendor and
# run that binary. See docker/compat/composer.json.
#
# --user stops composer from making a vendor/ owned by root in the working
# tree. Without it, the removal of docker/compat/vendor needs sudo.
compat-deps: ## Install the pinned PHPCompatibility toolchain (docker/compat)
	@test -x docker/compat/vendor/bin/phpcs || \
		$(TOOLS) --user $(shell id -u):$(shell id -g) --entrypoint sh qa -c \
		'export COMPOSER_HOME=/tmp/ch HOME=/tmp; cd /project/docker/compat && \
		 composer install --no-interaction --no-progress'

# phpcs exits non-zero when it finds a problem. This is not a failure of the
# harness, so `make audit` also exits non-zero on findings.
compat: compat-deps ## PHP 8.2 compatibility scan (PHPCompatibility)
	@$(TOOLS) --entrypoint sh qa -c \
		'docker/compat/vendor/bin/phpcs --standard=docker/phpcs.xml .'

stan: ## PHPStan (level 0 baseline — raise as the code improves)
	$(TOOLS) qa phpstan analyse --level=0 --no-progress \
		Endpointman.class.php *.class.php lib includes views || true

audit: lint compat ## Run lint + compatibility scan

# The anacrypt test starts as root, makes its files, and then runs the checks
# as www-data. The php service runs as root, so it can do both.
test: ## Run the PHP tests in docker/tests (no FreePBX needed)
	@for t in docker/tests/*_test.php; do \
		echo "--- $$t"; $(TOOLS) php $$t || exit 1; \
	done

## ---- vendored assets -------------------------------------------------------

# CodeMirror is a vendored upstream build. These are the paths that the
# module uses. FreePBX does NOT load assets/js/addon/ and assets/js/mode/
# automatically, as it does for assets/js/*.js. views/settings.js and
# views/epm_templates.js add them.
#
# Stay on 5.x. It is the last version with the API that this module uses: a
# global CodeMirror object and CodeMirror.fromTextArea(). 6.x changes both
# and needs a bundler, which this tree does not have.
#
# Do not patch the files after the fetch. The one local rule, the fullscreen
# inset that keeps the editor below the FreePBX navbar, is in
# assets/css/views/settings.css.
CM_VERSION := 5.65.21
CM_BASE    := https://cdnjs.cloudflare.com/ajax/libs/codemirror/$(CM_VERSION)

codemirror: ## Re-fetch the vendored CodeMirror assets (pin: CM_VERSION)
	curl -fsS -o assets/js/codemirror.min.js               $(CM_BASE)/codemirror.min.js
	curl -fsS -o assets/js/addon/fullscreen.js             $(CM_BASE)/addon/display/fullscreen.js
	curl -fsS -o assets/js/addon/simplescrollbars.js       $(CM_BASE)/addon/scroll/simplescrollbars.js
	curl -fsS -o assets/js/mode/xml.js                     $(CM_BASE)/mode/xml/xml.js
	curl -fsS -o assets/css/codemirror.css                 $(CM_BASE)/codemirror.css
	curl -fsS -o assets/css/codemirror_fullscreen.css      $(CM_BASE)/addon/display/fullscreen.css
	curl -fsS -o assets/css/codemirror_simplescrollbars.css $(CM_BASE)/addon/scroll/simplescrollbars.css
	@grep -o 'version="[0-9.]*"' assets/js/codemirror.min.js

## ---- translations ----------------------------------------------------------

# Needs GNU gettext on the host (xgettext, msgmerge, msgfmt), as `package`
# needs rsync and tar.
#
# The eight .po files contain some translations, most of them in es_ES. Thus
# merge and do not start again. msgmerge keeps each translation whose msgid
# is unchanged and marks moved strings as fuzzy, for a translator to check.
# msgfmt then builds the .mo files that FreePBX reads.
#
# lib/provisioner/ is a vendored third-party tree, so do not translate it.
# The JavaScript of the module is not scanned. Its visible strings are plain
# English literals, not gettext calls.
I18N_POT     := i18n/endpointman.pot
I18N_SOURCES := $(shell find . -name '*.php' -not -path './lib/provisioner/*' \
                  -not -path './build/*' -not -path './docker/*' | sort)

i18n: ## Regenerate i18n/endpointman.pot, then merge and compile every locale
	@command -v xgettext >/dev/null && command -v msgmerge >/dev/null \
		&& command -v msgfmt >/dev/null \
		|| { echo "need GNU gettext: xgettext, msgmerge, msgfmt" >&2; exit 1; }
	@mkdir -p build
	xgettext --language=PHP --from-code=UTF-8 --keyword=_ \
		--package-name=$(RAWNAME) --package-version=$(VERSION) \
		--copyright-holder='Sangoma Technologies, Inc.' \
		--msgid-bugs-address='' -o build/$(RAWNAME).pot $(I18N_SOURCES)
	@# Keep the FreePBX preamble the module has always shipped, and the UTF-8
	@# charset xgettext leaves as CHARSET when it is not told one.
	@{ printf '%s\n' \
		'# This file is part of FreePBX.' \
		'#' \
		'# For licensing information, please see the file named LICENSE located in the module directory' \
		'#' \
		'# FreePBX language template for $(RAWNAME)' \
		'# Copyright (C) 2008-$(shell date +%Y) Sangoma Technologies, Inc.' \
		'#'; \
	   sed -n '/^msgid ""/,$$p' build/$(RAWNAME).pot \
	     | sed 's/charset=CHARSET/charset=utf-8/'; } > $(I18N_POT)
	@rm -f build/$(RAWNAME).pot
	@for po in i18n/*/LC_MESSAGES/$(RAWNAME).po; do \
		msgmerge --quiet --previous --update --backup=none $$po $(I18N_POT); \
		msgfmt -o $${po%.po}.mo $$po; \
		printf '%-34s %s\n' "$$po" "$$(msgfmt --statistics -o /dev/null $$po 2>&1)"; \
	done

## ---- housekeeping ----------------------------------------------------------

fix-perms: ## Reclaim ownership after LIVE mode chowns the bind mount
	sudo chown -R $(shell id -u):$(shell id -g) .
