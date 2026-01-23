/**
 * GraphQL Types for XQUANTORIA CMS
 */

// Common Scalars
export type DateTime = string;
export type Upload = File;

// Pagination Types
export interface PaginatorInfo {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  hasMorePages: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  paginatorInfo: PaginatorInfo;
}

// Post Types
export interface Post {
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  status: 'draft' | 'published' | 'scheduled' | 'pending_review';
  is_hidden: boolean;
  published_at: DateTime | null;
  view_count: number;
  meta_title: string | null;
  meta_description: string | null;
  meta_robots: string | null;
  language: string | null;
  created_at: DateTime;
  updated_at: DateTime;
  submitted_for_review_at: DateTime | null;
  approved_at: DateTime | null;
  changes_requested_at: DateTime | null;
  reviewer_feedback: string | null;
  is_scheduled?: boolean;
  is_published?: boolean;
  full_url?: string;

  author: User;
  featured_image: Media | null;
  categories: Category[];
  tags: Tag[];
  comments: Comment[];
  revisions: PostRevision[];
  social_shares: SocialShare[];
  downloads: Download[];
  assignees: User[];
  approved_by: User | null;
  translation_parent: Post | null;
  translations: Post[];
}

export interface CreatePostInput {
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  featured_image_id?: string;
  status: string;
  is_hidden?: boolean;
  published_at?: DateTime;
  meta_title?: string;
  meta_description?: string;
  meta_robots?: string;
  language?: string;
  translation_of_id?: string;
  category_ids?: string[];
  tag_ids?: string[];
  download_ids?: string[];
}

export interface UpdatePostInput {
  id: string;
  title?: string;
  slug?: string;
  content?: string;
  excerpt?: string;
  featured_image_id?: string;
  status?: string;
  is_hidden?: boolean;
  published_at?: DateTime;
  meta_title?: string;
  meta_description?: string;
  meta_robots?: string;
  language?: string;
  translation_of_id?: string;
  category_ids?: string[];
  tag_ids?: string[];
  download_ids?: string[];
}

// User Types
export interface User {
  id: string;
  name: string;
  email: string;
  display_name: string | null;
  role: 'admin' | 'editor' | 'author' | 'subscriber';
  avatar_url: string | null;
  bio: string | null;
  is_active: boolean;
  last_login_at: DateTime | null;
  preferred_locale: string | null;
  created_at: DateTime;
  updated_at: DateTime;
  email_verified_at: DateTime | null;
  has_two_factor_enabled: boolean;

  posts: Post[];
  media: Media[];
  assigned_posts: Post[];
  approved_posts: Post[];
}

export interface UpdateUserInput {
  id: string;
  name?: string;
  email?: string;
  display_name?: string;
  avatar_url?: string;
  bio?: string;
  role?: string;
  is_active?: boolean;
  preferred_locale?: string;
}

// Category Types
export interface Category {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  color: string | null;
  icon_url: string | null;
  meta_title: string | null;
  meta_description: string | null;
  language: string | null;
  created_at: DateTime;
  updated_at: DateTime;

  parent: Category | null;
  children: Category[];
  posts: Post[];
}

export interface CreateCategoryInput {
  name: string;
  slug: string;
  description?: string;
  parent_id?: string;
  color?: string;
  icon_url?: string;
  meta_title?: string;
  meta_description?: string;
  language?: string;
  translation_of_id?: string;
}

export interface UpdateCategoryInput {
  id: string;
  name?: string;
  slug?: string;
  description?: string;
  parent_id?: string;
  color?: string;
  icon_url?: string;
  meta_title?: string;
  meta_description?: string;
  language?: string;
  translation_of_id?: string;
}

// Tag Types
export interface Tag {
  id: string;
  name: string;
  slug: string;
  language: string | null;
  usage_count: number;
  created_at: DateTime;
  updated_at: DateTime;

  posts: Post[];
}

export interface CreateTagInput {
  name: string;
  slug: string;
  language?: string;
  translation_of_id?: string;
}

export interface UpdateTagInput {
  id: string;
  name?: string;
  slug?: string;
  language?: string;
  translation_of_id?: string;
}

// Media Types
export interface Media {
  id: string;
  filename: string;
  original_filename: string;
  mime_type: string;
  size: number;
  path: string;
  url: string;
  title: string | null;
  alt_text: string | null;
  width: number | null;
  height: number | null;
  created_at: DateTime;
  updated_at: DateTime;

  uploaded_by: User;
  posts: Post[];
}

export interface UpdateMediaInput {
  id: string;
  title?: string;
  alt_text?: string;
}

// Comment Types
export interface Comment {
  id: string;
  content: string;
  status: 'pending' | 'approved' | 'rejected' | 'spam';
  created_at: DateTime;
  updated_at: DateTime;

  post: Post;
  author: User;
  parent: Comment | null;
  replies: Comment[];
}

export interface CreateCommentInput {
  post_id: string;
  content: string;
  parent_id?: string;
}

export interface UpdateCommentInput {
  id: string;
  content?: string;
}

// PostRevision Types
export interface PostRevision {
  id: string;
  title: string;
  content: string;
  excerpt: string | null;
  created_at: DateTime;

  post: Post;
  author: User;
}

// SocialShare Types
export interface SocialShare {
  id: string;
  platform: string;
  share_count: number;
  created_at: DateTime;
  updated_at: DateTime;

  post: Post;
}

// Download Types
export interface Download {
  id: string;
  title: string;
  filename: string;
  size: number;
  download_count: number;
  created_at: DateTime;
  updated_at: DateTime;

  uploaded_by: User;
  posts: Post[];
}

// Page Types
export interface Page {
  id: string;
  title: string;
  slug: string;
  content: string;
  status: 'draft' | 'published';
  meta_title: string | null;
  meta_description: string | null;
  language: string | null;
  created_at: DateTime;
  updated_at: DateTime;

  author: User;
  translation_parent: Page | null;
  translations: Page[];
}

export interface CreatePageInput {
  title: string;
  slug: string;
  content: string;
  status: string;
  meta_title?: string;
  meta_description?: string;
  language?: string;
  translation_of_id?: string;
}

export interface UpdatePageInput {
  id: string;
  title?: string;
  slug?: string;
  content?: string;
  status?: string;
  meta_title?: string;
  meta_description?: string;
  language?: string;
  translation_of_id?: string;
}

// Query Variables Types
export interface GetPostsVariables {
  first?: number;
  page?: number;
  status?: string;
  category_id?: string;
  tag_id?: string;
  author_id?: string;
  search?: string;
  language?: string;
}

export interface GetUsersVariables {
  first?: number;
  page?: number;
  role?: string;
  search?: string;
}

export interface GetCategoriesVariables {
  first?: number;
  page?: number;
  parent_id?: string;
  language?: string;
}

export interface GetTagsVariables {
  first?: number;
  page?: number;
  search?: string;
  language?: string;
}

export interface GetMediaVariables {
  first?: number;
  page?: number;
  type?: string;
}

export interface GetCommentsVariables {
  first?: number;
  page?: number;
  post_id?: string;
  status?: string;
}
