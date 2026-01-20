import React from 'react';
import { Card, Row, Col, Tag, Space, Typography, Badge } from 'antd';
import {
  ClockCircleOutlined,
  EyeOutlined,
  CommentOutlined,
  StarOutlined,
} from '@ant-design/icons';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const { Text, Title, Paragraph } = Typography;
const { Meta } = Card;

interface EditorialArticle {
  id: number;
  title: string;
  excerpt: string;
  featured_image?: string;
  author: string;
  category: string;
  published_at: string;
  view_count: number;
  comment_count: number;
  is_featured: boolean;
  is_breaking: boolean;
}

interface EditorialNewsLayoutProps {
  articles: EditorialArticle[];
  layout?: 'classic' | 'modern' | 'magazine';
}

const EditorialNewsLayout: React.FC<EditorialNewsLayoutProps> = ({
  articles,
  layout = 'modern'
}) => {
  // Featured article (first)
  const featuredArticle = articles[0];
  const breakingNews = articles.filter(a => a.is_breaking);
  const topStories = articles.slice(0, 5);
  const latestNews = articles.slice(5);

  if (layout === 'classic') {
    return React.createElement('div', { className: 'editorial-layout-classic' },
      // Breaking News Banner
      breakingNews.length > 0 && React.createElement('div', {
        style: {
          background: '#ff4d4f',
          color: '#fff',
          padding: '8px 16px',
          marginBottom: 16,
          borderRadius: 4,
        }
      },
        React.createElement(Space, null,
          React.createElement(Badge, { status: 'processing', text: 'BREAKING NEWS' }),
          React.createElement(Text, { style: { color: '#fff' } }, breakingNews[0].title)
        )
      ),
      React.createElement(Row, { gutter: 16 },
        // Main Featured Article
        React.createElement(Col, { span: 16 },
          React.createElement(Card, { hoverable: true, cover: featuredArticle?.featured_image && React.createElement('div', {
            style: {
              height: 400,
              backgroundImage: 'url(' + featuredArticle.featured_image + ')',
              backgroundSize: 'cover',
              backgroundPosition: 'center',
            }
          }) },
            React.createElement(Meta, {
              title: React.createElement(Title, { level: 2, style: { marginBottom: 8 } }, featuredArticle?.title),
              description: React.createElement(Space, { direction: 'vertical', style: { width: '100%' } },
                React.createElement(Paragraph, { style: { fontSize: 16 } }, featuredArticle?.excerpt)
              )
            })
          )
        ),
        // Sidebar
        React.createElement(Col, { span: 8 },
          React.createElement(Space, { direction: 'vertical', style: { width: '100%' }, size: 'large' },
            // Trending
            React.createElement(Card, {
              title: React.createElement(Space, null, React.createElement(StarOutlined), ' Trending'),
              size: 'small'
            },
              React.createElement(Space, { direction: 'vertical', style: { width: '100%' } },
                topStories.map((article, index) =>
                  React.createElement(Card, {
                    key: article.id,
                    size: 'small',
                    type: 'inner',
                    style: { marginBottom: 8 }
                  },
                    React.createElement(Space, null,
                      React.createElement(Text, {
                        style: { fontSize: 18, fontWeight: 'bold', color: '#999' }
                      }, index + 1),
                      React.createElement('div', null,
                        React.createElement(Text, { strong: true }, article.title),
                        React.createElement('br'),
                        React.createElement(Text, {
                          type: 'secondary',
                          style: { fontSize: 12 }
                        }, article.view_count + ' views')
                      )
                    )
                  )
                )
              )
            )
          )
        )
      )
    );
  }

  if (layout === 'magazine') {
    return React.createElement('div', { className: 'editorial-layout-magazine' },
      React.createElement(Row, { gutter: [16, 16] },
        articles.map((article) =>
          React.createElement(Col, { key: article.id, xs: 24, sm: 12, md: 8, lg: 6 },
            React.createElement(Card, { hoverable: true, cover: article.featured_image && React.createElement('div', {
              style: {
                height: 200,
                backgroundImage: 'url(' + article.featured_image + ')',
                backgroundSize: 'cover',
                backgroundPosition: 'center',
              }
            }) },
              React.createElement(Meta, {
                title: React.createElement(Text, { ellipsis: true, style: { fontWeight: 500 } }, article.title),
                description: React.createElement(Space, { direction: 'vertical', style: { width: '100%' } },
                  React.createElement(Tag, { color: 'blue' }, article.category),
                  React.createElement(Text, {
                    ellipsis: true,
                    type: 'secondary',
                    style: { fontSize: 12 }
                  }, article.excerpt),
                  React.createElement(Space, null,
                    React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                      React.createElement(EyeOutlined), ' ' + article.view_count),
                    React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                      React.createElement(CommentOutlined), ' ' + article.comment_count)
                  )
                )
              })
            )
          )
        )
      )
    );
  }

  // Modern Layout (default)
  return React.createElement('div', { className: 'editorial-layout-modern' },
    // Hero Section with Featured Article
    React.createElement(Card, {
      className: 'editorial-hero',
      cover: featuredArticle?.featured_image && React.createElement('div', {
        style: {
          height: 500,
          backgroundImage: 'linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.8)), url(' + featuredArticle.featured_image + ')',
          backgroundSize: 'cover',
          backgroundPosition: 'center',
          display: 'flex',
          alignItems: 'flex-end',
          padding: 32,
        }
      },
        React.createElement('div', { style: { color: '#fff' } },
          React.createElement(Tag, { color: 'red', style: { marginBottom: 16 } }, 'FEATURED'),
          React.createElement(Title, { level: 2, style: { color: '#fff', marginBottom: 16 } }, featuredArticle?.title),
          React.createElement(Paragraph, { style: { color: '#fff', opacity: 0.9, fontSize: 16 } }, featuredArticle?.excerpt),
          React.createElement(Space, null,
            React.createElement(Text, { style: { color: '#fff' } }, 'By ' + featuredArticle?.author),
            React.createElement(Text, { style: { color: '#fff' } },
              React.createElement(ClockCircleOutlined), ' ' + dayjs(featuredArticle?.published_at).format('MMM DD, YYYY')
            )
          )
        )
      )
    }),
    React.createElement(Row, { gutter: 16, style: { marginTop: 16 } },
      // Main Column
      React.createElement(Col, { span: 16 },
        React.createElement(Space, { direction: 'vertical', style: { width: '100%' }, size: 'large' },
          // Latest News Grid
          React.createElement(Card, { title: React.createElement(Title, { level: 4 }, 'Latest Stories') },
            React.createElement(Row, { gutter: [16, 16] },
              latestNews.map((article) =>
                React.createElement(Col, { key: article.id, span: 12 },
                  React.createElement(Card, {
                    hoverable: true,
                    cover: article.featured_image && React.createElement('div', {
                      style: {
                        height: 180,
                        backgroundImage: 'url(' + article.featured_image + ')',
                        backgroundSize: 'cover',
                        backgroundPosition: 'center',
                      }
                    }),
                    bodyStyle: { padding: 12 }
                  },
                    React.createElement(Tag, { color: 'blue', style: { marginBottom: 8 } }, article.category),
                    React.createElement(Text, {
                      strong: true,
                      ellipsis: true,
                      style: { display: 'block', marginBottom: 8 }
                    }, article.title),
                    React.createElement(Space, { size: 'large' },
                      React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                        React.createElement(EyeOutlined), ' ' + article.view_count),
                      React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                        React.createElement(CommentOutlined), ' ' + article.comment_count),
                      React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                        React.createElement(ClockCircleOutlined), ' ' + dayjs(article.published_at).fromNow())
                    )
                  )
                )
              )
            )
          )
        )
      ),
      // Sidebar
      React.createElement(Col, { span: 8 },
        React.createElement(Space, { direction: 'vertical', style: { width: '100%' }, size: 'large' },
          // Top Stories
          React.createElement(Card, {
            title: React.createElement(Title, { level: 4 }, React.createElement(StarOutlined), ' Top Stories')
          },
            React.createElement(Space, { direction: 'vertical', style: { width: '100%' } },
              topStories.map((article, index) =>
                React.createElement('div', {
                  key: article.id,
                  style: {
                    borderBottom: '1px solid #f0f0f0',
                    paddingBottom: 12,
                    marginBottom: 12,
                  }
                },
                  React.createElement(Space, null,
                    React.createElement(Text, {
                      style: {
                        fontSize: 24,
                        fontWeight: 'bold',
                        color: index < 3 ? '#1890ff' : '#999',
                      }
                    }, index + 1),
                    React.createElement('div', null,
                      React.createElement(Text, { strong: true }, article.title),
                      React.createElement('br'),
                      React.createElement(Space, null,
                        React.createElement(Tag, { color: 'blue' }, article.category),
                        React.createElement(Text, { type: 'secondary', style: { fontSize: 12 } },
                          article.view_count + ' views')
                      )
                    )
                  )
                )
              )
            )
          ),
          // Newsletter Signup
          React.createElement(Card, { title: 'Newsletter' },
            React.createElement(Text, { type: 'secondary' }, 'Get the latest posts delivered to your inbox')
          )
        )
      )
    )
  );
};

export default EditorialNewsLayout;
