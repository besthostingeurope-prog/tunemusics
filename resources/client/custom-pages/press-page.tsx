import {useCustomPage} from '@common/custom-page/use-custom-page';
import {CustomPageBody} from '@common/custom-page/custom-page-body';
import {PageMetaTags} from '@common/http/page-meta-tags';
import {PageStatus} from '@common/http/page-status';
import {Trans} from '@ui/i18n/trans';
import {Link} from 'react-router';

export function Component() {
  const query = useCustomPage('press-tunemusics-launches-new-platform');

  return (
    <div className="min-h-full p-16 md:p-32 max-w-5xl mx-auto">
      <PageMetaTags query={query} />
      <div className="mb-24 pb-16 border-b border-divider">
        <h1 className="text-3xl font-extrabold tracking-tight mb-8">
          <Trans message="Press Releases & Newsroom" />
        </h1>
        <p className="text-muted text-sm">
          <Trans message="Official announcements, media kits, and updates from TuneMusics." />
        </p>
      </div>

      <div className="grid gap-16 md:grid-cols-2 mb-32">
        <Link
          to="/pages/press-tunemusics-launches-new-platform"
          className="p-20 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 block"
        >
          <span className="text-xs uppercase font-semibold text-primary mb-6 block">
            Press Release
          </span>
          <h2 className="text-lg font-bold mb-8">
            TuneMusics Launches a New Platform for Artists and AI Music Creators
          </h2>
          <p className="text-xs text-muted line-clamp-3">
            The London-based platform gives independent musicians, AI-assisted creators, virtual artists, and emerging talent a new opportunity to be heard.
          </p>
        </Link>
        <Link
          to="/pages/press-tunemusics-where-fans-pay-artists"
          className="p-20 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 block"
        >
          <span className="text-xs uppercase font-semibold text-primary mb-6 block">
            Official Announcement
          </span>
          <h2 className="text-lg font-bold mb-8">
            The Streaming Platform Where Fans Actually Pay Artists
          </h2>
          <p className="text-xs text-muted line-clamp-3">
            Real music, real fans, real earnings. Transparent streaming without algorithmic suppression or diluted payouts.
          </p>
        </Link>
      </div>

      {query.data?.page ? (
        <div className="mt-24 p-24 rounded-panel bg-elevated/50 border border-divider">
          <CustomPageBody page={query.data.page} />
        </div>
      ) : (
        <PageStatus query={query} loaderClassName="mt-40" />
      )}
    </div>
  );
}
