#!/usr/bin/env bash

set -o errexit -o noclobber -o pipefail

RUBY_VERSION=3.0.3

if ! command -v rbenv &> /dev/null; then
    brew install rbenv ruby-build gobject-introspection gdk-pixbuf
else
    echo "rbenv already installed"
fi

if ! ruby -v | grep $RUBY_VERSION; then
    if ! rbenv versions | grep $RUBY_VERSION; then
        rbenv install $RUBY_VERSION && echo "ruby $RUBY_VERSION installed"
    fi
    rbenv global $RUBY_VERSION
else
    echo "ruby $RUBY_VERSION already installed"
fi

eval "$(rbenv init - zsh)"
gem env

local_install_printableCardAppender() {
    cd ..
    git clone https://github.com/davidoliviersaban/printableCardsAppender.git
    ./gradlew build
}

rbenv global $RUBY_VERSION
gem list | grep pkg-config || gem install pkg-config && echo "pkg-config installed"
gem list | grep squib || gem install squib && echo "squib installed"
gem list | grep game_icons || gem install game_icons && echo "game_icons installed"

gem update --system

rm -rf _output* _tiles* _levels*
ruby src/ruby/deck.rb
ruby src/ruby/tiles.rb
ruby src/ruby/level.rb

if [ ! -d "../printableCardsAppender" ]; then
    local_install_printableCardAppender
fi

cd ../printableCardsAppender

./gradlew appendCards --args="'../hackNslash/_output' '../hackNslash/imagesToPrint/cards' A4 false"
./gradlew appendCards --args="'../hackNslash/_tiles' '../hackNslash/imagesToPrint/tiles' A4 false"
./gradlew appendCards --args="'../hackNslash/_levels' '../hackNslash/imagesToPrint/levels' A4 false"
