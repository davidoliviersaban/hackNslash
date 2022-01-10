
rm -rf _output* _tiles* _levels*
ruby src/ruby/deck.rb
ruby src/ruby/tiles.rb
ruby src/ruby/level.rb
cd ../printableCardsAppender
./gradlew appendCards --args="'../hack N slash/_output' '../hack N slash/imagesToPrint/cards'   ISO_A4 false"
./gradlew appendCards --args="'../hack N slash/_tiles' '../hack N slash/imagesToPrint/tiles'   ISO_A4 false"
./gradlew appendCards --args="'../hack N slash/_levels' '../hack N slash/imagesToPrint/levels'   ISO_A4 false"
