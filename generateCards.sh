
rm -rf _output* _terrain*
ruby src/ruby/deck.rb
ruby src/ruby/tiles.rb
cd ../printableCardsAppender
./gradlew appendCard --args="'../hack N slash/_output' '../hack N slash/imagesToPrint/cards'   A4 false"
./gradlew appendCard --args="'../hack N slash/_tiles' '../hack N slash/imagesToPrint/tiles'   A4 false"
