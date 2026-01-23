import { gql } from '@apollo/client';

// Post Mutations
export const CREATE_POST = gql`
  mutation CreatePost($input: CreatePostInput!) {
    createPost(input: $input) {
      id
      title
      slug
      content
      excerpt
      status
      is_hidden
      published_at
      language
      created_at
      updated_at
      author {
        id
        name
      }
      categories {
        id
        name
      }
      tags {
        id
        name
      }
    }
  }
`;

export const UPDATE_POST = gql`
  mutation UpdatePost($input: UpdatePostInput!) {
    updatePost(input: $input) {
      id
      title
      slug
      content
      excerpt
      status
      is_hidden
      published_at
      language
      updated_at
      author {
        id
        name
      }
      categories {
        id
        name
      }
      tags {
        id
        name
      }
    }
  }
`;

export const DELETE_POST = gql`
  mutation DeletePost($id: ID!) {
    deletePost(id: $id) {
      id
      title
    }
  }
`;

export const SUBMIT_FOR_REVIEW = gql`
  mutation SubmitForReview($id: ID!) {
    submitForReview(id: $id) {
      id
      status
      submitted_for_review_at
    }
  }
`;

export const APPROVE_POST = gql`
  mutation ApprovePost($id: ID!, $feedback: String) {
    approvePost(id: $id, feedback: $feedback) {
      id
      status
      approved_at
      reviewer_feedback
      approved_by {
        id
        name
      }
    }
  }
`;

export const REQUEST_CHANGES = gql`
  mutation RequestChanges($id: ID!, $feedback: String!) {
    requestChanges(id: $id, feedback: $feedback) {
      id
      status
      reviewer_feedback
      changes_requested_at
    }
  }
`;

// Category Mutations
export const CREATE_CATEGORY = gql`
  mutation CreateCategory($input: CreateCategoryInput!) {
    createCategory(input: $input) {
      id
      name
      slug
      description
      color
      icon_url
      language
      created_at
    }
  }
`;

export const UPDATE_CATEGORY = gql`
  mutation UpdateCategory($input: UpdateCategoryInput!) {
    updateCategory(input: $input) {
      id
      name
      slug
      description
      color
      icon_url
      language
      updated_at
    }
  }
`;

export const DELETE_CATEGORY = gql`
  mutation DeleteCategory($id: ID!) {
    deleteCategory(id: $id) {
      id
      name
    }
  }
`;

// Tag Mutations
export const CREATE_TAG = gql`
  mutation CreateTag($input: CreateTagInput!) {
    createTag(input: $input) {
      id
      name
      slug
      language
      created_at
    }
  }
`;

export const UPDATE_TAG = gql`
  mutation UpdateTag($input: UpdateTagInput!) {
    updateTag(input: $input) {
      id
      name
      slug
      language
      updated_at
    }
  }
`;

export const DELETE_TAG = gql`
  mutation DeleteTag($id: ID!) {
    deleteTag(id: $id) {
      id
      name
    }
  }
`;

// User Mutations
export const UPDATE_USER = gql`
  mutation UpdateUser($input: UpdateUserInput!) {
    updateUser(input: $input) {
      id
      name
      email
      display_name
      role
      avatar_url
      bio
      is_active
      preferred_locale
      updated_at
    }
  }
`;

export const DELETE_USER = gql`
  mutation DeleteUser($id: ID!) {
    deleteUser(id: $id) {
      id
      name
    }
  }
`;

// Comment Mutations
export const CREATE_COMMENT = gql`
  mutation CreateComment($input: CreateCommentInput!) {
    createComment(input: $input) {
      id
      content
      status
      created_at
      post {
        id
        title
      }
      author {
        id
        name
      }
    }
  }
`;

export const UPDATE_COMMENT = gql`
  mutation UpdateComment($input: UpdateCommentInput!) {
    updateComment(input: $input) {
      id
      content
      updated_at
    }
  }
`;

export const DELETE_COMMENT = gql`
  mutation DeleteComment($id: ID!) {
    deleteComment(id: $id) {
      id
      content
    }
  }
`;

export const MODERATE_COMMENT = gql`
  mutation ModerateComment($id: ID!, $status: String!) {
    moderateComment(id: $id, status: $status) {
      id
      status
      updated_at
    }
  }
`;

// Media Mutations
export const UPLOAD_MEDIA = gql`
  mutation UploadMedia($file: Upload!, $title: String, $alt_text: String) {
    uploadMedia(file: $file, title: $title, alt_text: $alt_text) {
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
    }
  }
`;

export const UPDATE_MEDIA = gql`
  mutation UpdateMedia($input: UpdateMediaInput!) {
    updateMedia(input: $input) {
      id
      title
      alt_text
      updated_at
    }
  }
`;

export const DELETE_MEDIA = gql`
  mutation DeleteMedia($id: ID!) {
    deleteMedia(id: $id) {
      id
      filename
    }
  }
`;

// Page Mutations
export const CREATE_PAGE = gql`
  mutation CreatePage($input: CreatePageInput!) {
    createPage(input: $input) {
      id
      title
      slug
      content
      status
      language
      created_at
      author {
        id
        name
      }
    }
  }
`;

export const UPDATE_PAGE = gql`
  mutation UpdatePage($input: UpdatePageInput!) {
    updatePage(input: $input) {
      id
      title
      slug
      content
      status
      language
      updated_at
    }
  }
`;

export const DELETE_PAGE = gql`
  mutation DeletePage($id: ID!) {
    deletePage(id: $id) {
      id
      title
    }
  }
`;
