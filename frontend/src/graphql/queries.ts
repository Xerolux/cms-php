import { gql } from '@apollo/client';

// Post Queries
export const GET_PUBLIC_POSTS = gql`
  query GetPublicPosts(
    $first: Int
    $page: Int
    $category_id: ID
    $tag_id: ID
    $search: String
    $language: String
  ) {
    publicPosts(
      first: $first
      page: $page
      category_id: $category_id
      tag_id: $tag_id
      search: $search
      language: $language
    ) {
      data {
        id
        title
        slug
        excerpt
        content
        status
        published_at
        view_count
        language
        created_at
        updated_at
        author {
          id
          name
          display_name
          avatar_url
        }
        featured_image {
          id
          url
          title
          alt_text
        }
        categories {
          id
          name
          slug
          color
        }
        tags {
          id
          name
          slug
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        perPage
        total
        hasMorePages
      }
    }
  }
`;

export const GET_POSTS = gql`
  query GetPosts(
    $first: Int
    $page: Int
    $status: String
    $category_id: ID
    $tag_id: ID
    $author_id: ID
    $search: String
    $language: String
  ) {
    posts(
      first: $first
      page: $page
      status: $status
      category_id: $category_id
      tag_id: $tag_id
      author_id: $author_id
      search: $search
      language: $language
    ) {
      data {
        id
        title
        slug
        excerpt
        status
        is_hidden
        published_at
        view_count
        language
        created_at
        updated_at
        submitted_for_review_at
        approved_at
        author {
          id
          name
          display_name
          avatar_url
        }
        featured_image {
          id
          url
          title
          alt_text
        }
        categories {
          id
          name
          slug
          color
        }
        tags {
          id
          name
          slug
        }
        assignees {
          id
          name
          display_name
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        perPage
        total
        hasMorePages
      }
    }
  }
`;

export const GET_POST = gql`
  query GetPost($id: ID!) {
    post(id: $id) {
      id
      title
      slug
      content
      excerpt
      status
      is_hidden
      published_at
      view_count
      meta_title
      meta_description
      meta_robots
      language
      created_at
      updated_at
      submitted_for_review_at
      approved_at
      changes_requested_at
      reviewer_feedback
      author {
        id
        name
        display_name
        avatar_url
        bio
      }
      featured_image {
        id
        url
        title
        alt_text
        width
        height
      }
      categories {
        id
        name
        slug
        description
        color
      }
      tags {
        id
        name
        slug
      }
      downloads {
        id
        title
        filename
        size
        download_count
      }
      assignees {
        id
        name
        display_name
        avatar_url
      }
      approved_by {
        id
        name
        display_name
      }
      translation_parent {
        id
        title
        language
      }
      translations {
        id
        title
        language
        slug
      }
    }
  }
`;

export const GET_PUBLIC_POST_BY_SLUG = gql`
  query GetPublicPostBySlug($slug: String!) {
    publicPostBySlug(slug: $slug) {
      id
      title
      slug
      content
      excerpt
      published_at
      view_count
      meta_title
      meta_description
      author {
        id
        name
        display_name
        avatar_url
      }
      featured_image {
        id
        url
        title
        alt_text
      }
      categories {
        id
        name
        slug
      }
      tags {
        id
        name
        slug
      }
      full_url
      is_published
    }
  }
`;

// Category Queries
export const GET_CATEGORIES = gql`
  query GetCategories($first: Int, $page: Int, $parent_id: ID, $language: String) {
    categories(first: $first, page: $page, parent_id: $parent_id, language: $language) {
      data {
        id
        name
        slug
        description
        color
        icon_url
        language
        parent_id
        created_at
        updated_at
        parent {
          id
          name
          slug
        }
        children {
          id
          name
          slug
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

export const GET_PUBLIC_CATEGORIES = gql`
  query GetPublicCategories($first: Int, $page: Int, $language: String) {
    publicCategories(first: $first, page: $page, language: $language) {
      data {
        id
        name
        slug
        description
        color
        icon_url
        language
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

// Tag Queries
export const GET_TAGS = gql`
  query GetTags($first: Int, $page: Int, $search: String, $language: String) {
    tags(first: $first, page: $page, search: $search, language: $language) {
      data {
        id
        name
        slug
        language
        usage_count
        created_at
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

export const GET_PUBLIC_TAGS = gql`
  query GetPublicTags($first: Int, $page: Int, $language: String) {
    publicTags(first: $first, page: $page, language: $language) {
      data {
        id
        name
        slug
        language
        usage_count
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

// User Queries
export const GET_USERS = gql`
  query GetUsers($first: Int, $page: Int, $role: String, $search: String) {
    users(first: $first, page: $page, role: $role, search: $search) {
      data {
        id
        name
        email
        display_name
        role
        avatar_url
        bio
        is_active
        last_login_at
        created_at
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

export const GET_ME = gql`
  query GetMe {
    me {
      id
      name
      email
      display_name
      role
      avatar_url
      bio
      is_active
      preferred_locale
      created_at
      email_verified_at
      has_two_factor_enabled
    }
  }
`;

// Media Queries
export const GET_MEDIA = gql`
  query GetMedia($first: Int, $page: Int, $type: String) {
    media(first: $first, page: $page, type: $type) {
      data {
        id
        filename
        original_filename
        mime_type
        size
        url
        title
        alt_text
        width
        height
        created_at
        uploaded_by {
          id
          name
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

// Comment Queries
export const GET_COMMENTS = gql`
  query GetComments($first: Int, $page: Int, $post_id: ID, $status: String) {
    comments(first: $first, page: $page, post_id: $post_id, status: $status) {
      data {
        id
        content
        status
        created_at
        updated_at
        post {
          id
          title
        }
        author {
          id
          name
          display_name
          avatar_url
        }
        parent {
          id
          content
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

// Page Queries
export const GET_PAGES = gql`
  query GetPages($first: Int, $page: Int, $status: String, $language: String) {
    pages(first: $first, page: $page, status: $status, language: $language) {
      data {
        id
        title
        slug
        status
        language
        created_at
        updated_at
        author {
          id
          name
          display_name
        }
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;

export const GET_PUBLIC_PAGES = gql`
  query GetPublicPages($first: Int, $page: Int, $language: String) {
    publicPages(first: $first, page: $page, language: $language) {
      data {
        id
        title
        slug
        content
        meta_title
        meta_description
        language
      }
      paginatorInfo {
        currentPage
        lastPage
        total
      }
    }
  }
`;
