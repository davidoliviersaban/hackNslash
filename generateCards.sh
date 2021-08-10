
rm -rf _output* _terrain*
ruby src/ruby/deck.rb
cd ../printableCardsAppender
./gradlew appendCard --args="'../hack N slash/_output' '../hack N slash/imagesToPrint/cards'   A4 false"
