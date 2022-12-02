/* eslint-disable array-callback-return */
import { load } from 'cheerio';
import { Entry } from 'lambda/entry.model';

export interface Options {
  dictionaryId: string;
  entryClass: string;
}

interface ParseEntry extends Options {
  guid: string;
  letterHead: string;
  sortIndex: number;
  entryData: string;
}

export class FlexXhtmlParser {
  protected options: Options;

  protected toBeParsed: string;

  public parsedLetters: string[];

  public parsedEntries: Entry[];

  public constructor(toBeParsed: string, options: Partial<Options> = {}) {
    this.toBeParsed = toBeParsed;

    this.options = Object.assign(options);

    this.parsedLetters = [];

    this.parsedEntries = this.parseBody();
  }

  protected parseBody(): Entry[] {
    const $ = load(this.toBeParsed);

    const entries: Entry[] = [];
    let letterHead = '';
    $(`div.${this.options.entryClass}`).map((index, elem) => {
      const guid = $(elem).attr('id');
      const entryData = $(elem).html();
      if (guid && entryData) {
        // This can be two letters, like 'A a'
        const newLetterHead = $(elem)
          .prev('.letHead')
          .children('span.letter')
          .text()
          .split(' ')
          .pop();

        if (newLetterHead && newLetterHead !== letterHead) {
          letterHead = newLetterHead;
          this.parsedLetters.push(letterHead);
        }

        const sortIndex = index * 100;

        entries.push(
          FlexXhtmlParser.parseEntry({
            dictionaryId: this.options.dictionaryId.toLowerCase(),
            entryClass: this.options.entryClass,
            guid,
            letterHead,
            sortIndex,
            entryData,
          }),
        );
      }
    });

    return entries;
  }

  public static parseEntry({
    dictionaryId,
    entryClass,
    guid,
    letterHead,
    sortIndex,
    entryData,
  }: ParseEntry): Entry {
    // const $ = cheerio.load(entryData);

    // NOTE: guid field in Webonary and FLex actually includes the character 'g' at the beginning
    const displayXhtml = `<div class="${entryClass}" id="${guid}">${entryData}</div>`;

    const entry: Entry = {
      guid,
      dictionaryId,
      letterHead,
      sortIndex,
      displayXhtml,
    };

    return entry;
  }
}
