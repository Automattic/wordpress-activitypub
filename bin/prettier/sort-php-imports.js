/**
 * Prettier plugin for sorting PHP namespace imports
 */

// This plugin sorts PHP namespace imports alphabetically
module.exports = {
  languages: [
    {
      name: 'php',
      parsers: ['php'],
      extensions: ['.php'],
      vscodeLanguageIds: ['php']
    }
  ],
  parsers: {
    php: {
      parse: (text, parsers, options) => {
        // Use the built-in PHP parser first
        const phpParser = require('prettier/parser-php').parsers.php;
        return phpParser.parse(text, parsers, options);
      },
      astFormat: 'php',
      locStart: (node) => node.start,
      locEnd: (node) => node.end,
      preprocess: (text, options) => {
        // Find blocks of use statements
        const useStatementRegex = /^use\s+([^;]+);/gm;
        let match;
        let blocks = [];
        let lastIndex = 0;
        
        // Find all blocks of consecutive use statements
        while ((match = useStatementRegex.exec(text)) !== null) {
          const currentIndex = match.index;
          const fullMatch = match[0];
          
          // Check if this is part of an existing block or a new block
          if (blocks.length > 0) {
            const lastBlock = blocks[blocks.length - 1];
            // If this statement is close to the previous one, add it to the same block
            if (currentIndex - (lastBlock.end) < 3) {
              lastBlock.statements.push(fullMatch);
              lastBlock.end = currentIndex + fullMatch.length;
              continue;
            }
          }
          
          // Start a new block
          blocks.push({
            start: currentIndex,
            end: currentIndex + fullMatch.length,
            statements: [fullMatch]
          });
        }
        
        // Sort each block of use statements
        let result = text;
        let offset = 0;
        
        blocks.forEach(block => {
          // Sort the statements alphabetically
          const sortedStatements = [...block.statements].sort();
          
          // Replace the original block with sorted statements
          const originalBlock = result.substring(
            block.start + offset, 
            block.end + offset
          );
          const sortedBlock = sortedStatements.join("\n");
          
          if (originalBlock !== sortedBlock) {
            result = 
              result.substring(0, block.start + offset) + 
              sortedBlock + 
              result.substring(block.end + offset);
            
            // Update offset for subsequent replacements
            offset += sortedBlock.length - originalBlock.length;
          }
        });
        
        return result;
      }
    }
  },
  printers: {
    php: {
      print: (path, options, print) => {
        // Use the built-in PHP printer
        const phpPrinter = require('prettier/parser-php').printers.php;
        return phpPrinter.print(path, options, print);
      }
    }
  }
};
