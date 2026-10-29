import {useCustomPage} from '@common/custom-page/use-custom-page';
import {CustomPageBody} from '@common/custom-page/custom-page-body';
import {PageMetaTags} from '@common/http/page-meta-tags';
import {PageStatus} from '@common/http/page-status';
import {Trans} from '@ui/i18n/trans';
import {Link} from 'react-router';

export function Component() {
  const query = useCustomPage('blog-auto-album-54-still-walking');

  return (
    <div className="min-h-full p-16 md:p-32 max-w-5xl mx-auto">
      <PageMetaTags query={query} />
      <div className="mb-24 pb-16 border-b border-divider">
        <h1 className="text-3xl font-extrabold tracking-tight mb-8">
          <Trans message="TuneMusics Blog" />
        </h1>
        <p className="text-muted text-sm">
          <Trans message="Artist spotlights, albums of the day, and music trends." />
        </p>
      </div>

      <div className="grid gap-16 md:grid-cols-3 mb-32">
        <Link
          to="/pages/blog-auto-album-54-still-walking"
          className="p-16 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 block"
        >
          <span className="text-xs uppercase font-semibold text-primary mb-4 block">
            Album of the Day
          </span>
          <h2 className="text-base font-bold mb-4">Still Walking</h2>
          <p className="text-xs text-muted">A listener favorite album on TuneMusics.</p>
        </Link>
        <Link
          to="/pages/blog-auto-artist-189-the-weeknd"
          className="p-16 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 block"
        >
          <span className="text-xs uppercase font-semibold text-primary mb-4 block">
            Artist Spotlight
          </span>
          <h2 className="text-base font-bold mb-4">The Weeknd</h2>
          <p className="text-xs text-muted">Global streaming record-breaker.</p>
        </Link>
        <Link
          to="/pages/blog-auto-album-513-fear"
          className="p-16 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 block"
        >
          <span className="text-xs uppercase font-semibold text-primary mb-4 block">
            Album Spotlight
          </span>
          <h2 className="text-base font-bold mb-4">FEAR - NF</h2>
          <p className="text-xs text-muted">Top rated hip-hop project.</p>
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
