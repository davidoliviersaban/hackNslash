
rm -rf _output* _tiles* _levels*
ruby src/ruby/deck.rb
ruby src/ruby/tiles.rb
cd ../printableCardsAppender
./gradlew appendCard --args="'../hack N slash/_output' '../hack N slash/imagesToPrint/cards'   A4 false"
./gradlew appendCard --args="'../hack N slash/_tiles' '../hack N slash/imagesToPrint/tiles'   A4 false"
./gradlew appendCard --args="'../hack N slash/_levels' '../hack N slash/imagesToPrint/levels'   A4 false"
